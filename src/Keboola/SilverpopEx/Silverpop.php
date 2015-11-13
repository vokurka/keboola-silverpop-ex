<?php
require_once "Util/Exception.php";
require_once "Util/ArrayToXML.php";
require_once "Util/EngagePod.php";

class Silverpop
{
  private $config;
  private $destination;
  private $mandatoryConfigColumns = array(
    'bucket', 
    'username', 
    'password', 
    'engage_server', 
    'date_from', 
    'date_to',
    'export_aggregated_reports',
    'export_contact_lists',
    'export_events',
    'lists_to_download',
  );
  private $filesDownloaded = array();
  private $remoteDir = 'download/';
  private $localDir = '/tmp/';
  private $destinationFolder;

  //exportList
  //rawRecipientDataExport

  public function __construct($ymlConfig, $destinationFolder)
  {
    date_default_timezone_set('UTC');
    $this->destinationFolder = $destinationFolder;

    foreach ($this->mandatoryConfigColumns as $c)
    {
      if (!isset($ymlConfig[$c])) 
      {
        throw new SilverpopException("Mandatory column '{$c}' not found or empty.");
      }

      $this->config[$c] = $ymlConfig[$c];
    }

    $this->config['sftp_port'] = 22;
    $this->config['sftp_username'] = $this->sanitizeUsername($ymlConfig['username']);

    foreach (array('date_from', 'date_to') as $dateId)
    {
      $timestamp = strtotime($this->config[$dateId]);

      if ($timestamp === FALSE)
      {
        throw new SilverpopException("Invalid time value in field ".$dateId);
      }

      $dateTime = new DateTime();
      $dateTime->setTimestamp($timestamp);

      $this->config[$dateId] = $dateTime->format('m/d/Y H:i:s');
    }

    if (!is_array($this->config['lists_to_download']))
    {
      throw new SilverpopException("Country list must be an array.");
    }
  }

  private function logMessage($message)
  {
    echo($message."\n");
  }

  public function run()
  {
    // Initialize the library
    $silverpop = new EngagePod(array(
      'username'       => $this->config['username'],
      'password'       => $this->config['password'],
      'engage_server'  => $this->config['engage_server'],
    ));

    // export aggregated metrics
    if ($this->config['export_aggregated_reports'] === 1)
    {
      $this->exportAggregatedMetrics($silverpop);
    }

    // export contact list
    if ($this->config['export_contact_lists'] === 1)
    {
      $this->exportContactLists($silverpop);
    }

    // export events
    if ($this->config['export_events'] === 1)
    {
      $this->exportEvents($silverpop);
    }

    // extract and consolidate all the files
    foreach ($this->filesDownloaded as $f)
    {
      $this->extractAndLoad($f, $this->config['bucket']);
    }
  }

  private function sanitizeUsername($username)
  {
    return str_replace('@', '%40', $username);
  }

  private function exportAggregatedMetrics($silverpop)
  {
    $this->logMessage('Downloading aggregated metrics.');

    $mailings = $silverpop->getSentMailingsForOrg($this->config['date_from'], $this->config['date_to']);
    
    $this->logMessage('Downloading '.count($mailings).' mailings.');

    foreach ($mailings as $m)
    {
      $this->logMessage('Creating job for mailing '.$m['MailingId']);

      $result = $silverpop->trackingMetricExport($m['MailingId'], $this->config['date_from'], $this->config['date_to']);

      $this->downloadJob($result, $silverpop, 'aggregated_metrics');

      $this->logMessage('Job completed for mailing '.$m['MailingId']);
    }

    $this->logMessage('Download completed for aggregated metrics.');
  }

  private function exportContactLists($silverpop)
  {
    $this->logMessage('Downloading contact lists.');

    foreach ($this->config['lists_to_download'] as $list)
    {
      $result = $silverpop->exportList($list, $this->config['date_from'], $this->config['date_to']);

      $this->downloadJob($result, $silverpop, 'contact_lists');
    }

    $this->logMessage('Download completed for contact lists.');
  }

  private function exportEvents($silverpop)
  {
    $this->logMessage('Downloading events.');

    foreach ($this->config['lists_to_download'] as $country)
    {
      $result = $silverpop->exportList($country, $this->config['date_from'], $this->config['date_to']);

      $this->downloadJob($result, $silverpop, 'events');
    }

    $this->logMessage('Download completed for events.');
  }

  private function downloadJob($result, $silverpop, $type)
  {
    $file = str_replace('/download/', '', $result['FILE_PATH']);

    // Wait till its done
    $counter = 0;
    do
    {
    	sleep(2);

    	$status = $silverpop->getJobStatus($result['JOB_ID']);
      $counter++;
    } while ($status['JOB_STATUS'] != 'COMPLETE' && $counter < 30);

    // Check if everything happend OK
    if ($status['JOB_STATUS'] != 'COMPLETE')
    {
      throw new SilverpopException('An error occured while creating report in Silverpop.');
    }

    $this->logMessage('Job finished for ID '.$result['JOB_ID']);

    // ================== Download data from SFTP ==================
    if (!function_exists("ssh2_connect")){
      throw new SilverpopException('Function ssh2_connect not found, you cannot use ssh2 here');
    }

    if (!$connection = ssh2_connect('transfer'.$this->config['engage_server'].'.silverpop.com', $this->config['sftp_port'])){
      throw new SilverpopException('Unable to connect');
    }

    if (!ssh2_auth_password($connection, $this->config['username'], $this->config['password'])){
      throw new SilverpopException('Unable to authenticate.');
    }

    if (!$stream = ssh2_sftp($connection)){
      throw new SilverpopException('Unable to create a stream.');
    }

    if (!$dir = opendir("ssh2.sftp://{$stream}/{$this->remoteDir}")){
      throw new SilverpopException('Could not open the directory');
    }

    $remFile = htmlentities($file);
    if (!$remote = fopen("ssh2.sftp://{$stream}/{$this->remoteDir}{$remFile}", 'r'))
    {
      throw new SilverpopException("Unable to open remote file: $remFile");
    }

    if (!$local = @fopen($this->localDir . $file, 'w'))
    {
      throw new SilverpopException("Unable to create local file: $file");
    }

    stream_copy_to_stream($remote, $local);

    fclose($local);
    fclose($remote);

    // $data = file_get_contents("ssh2.sftp://{$stream}/{$this->remoteDir}{$file}");
    // file_put_contents($this->localDir . $file, $data);


    if ($type == 'contact_lists' || $type == 'events')
    {
      $this->loadFile($this->localDir.$file, $this->config['bucket'], $type, true);
    }
    else
    {
      $this->filesDownloaded[] = $file;
    }

    $this->logMessage('Data downloaded for job '.$result['JOB_ID']);
  }

  // Extracts file from zip and consolidates data into output file
  private function extractAndLoad($file, $bucket)
  {
    $writeHeader = false;
    $zipFolder = str_replace('.zip', '', $file);

    $zip = new ZipArchive;
    $res = $zip->open($this->localDir.$file);

    if ($res === TRUE) 
    {
      $zip->extractTo($this->localDir.$zipFolder);
      $zip->close();
    }

    foreach (glob($this->localDir.$zipFolder.'/*') as $file)
    {
      $fileName = explode('/', $file);
      $fileName = $fileName[count($fileName)-1];
      $fileName = str_replace('.csv', '', $fileName);

      $this->loadFile($file, $bucket, $fileName, $writeHeader);
    }
    
    $this->logMessage('Data extracted and loaded from file '.$zipFolder);
  }

    // loads CSV file and consolidates its data into destination file
  private function loadFile($file, $bucket, $destinationFile, $writeHeader)
  {
    $fileName = $bucket.'.'.$destinationFile;

    $source = fopen($file, "r");

    if ($source === false)
    {
      throw new SilverpopException("Unable to read: $file");
    }

    $header = fgets($source);

    if (!file_exists($this->destinationFolder.$fileName))
    {
      $writeHeader = true;
    }

    $destination = fopen($this->destinationFolder.$fileName, 'a');

    if ($destination === false)
    {
      throw new SilverpopException("Unable to write: {$this->destinationFolder}.{$fileName}");
    }

    if ($writeHeader == true)
    {
      $headerParts = explode(',', $header);
      
      foreach ($headerParts as $index => $part)
      {
        if (strlen($part) > 64)
        {
          $headerParts[$index] = substr($part, 0, 62).'"';
        }
      }

      $header = implode(',', $headerParts);

      fwrite($destination, $header);

      $writeHeader = false;
    }

    while ($row = fgets($source)) 
    {
      fwrite($destination, $row);
    }

    fclose($source);
    fclose($destination);
  }

}