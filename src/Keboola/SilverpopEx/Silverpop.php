<?php
require_once "Util/Exception.php";
require_once "Util/ArrayToXML.php";
require_once "Util/EngagePod.php";

class Silverpop
{
  private $config;
  private $source;
  private $destination;
  private $mandatoryConfigColumns = array('bucket', 'username', 'password', 'engage_server', 'date_from', 'date_to');
  private $configNamesIndexes;
  private $filesDownloaded;
  private $remoteDir = 'download/';
  private $localDir = '/tmp/';
  private $destinationFolder;

  public function __construct($source, $destinationFolder)
  {
    $this->destinationFolder = $destinationFolder;
    $fhIn = fopen($source, "r");
    $header = fgetcsv($fhIn);

    foreach ($this->mandatoryConfigColumns as $c)
    {
      if (!in_array($c, $header)) 
      {
        throw new SilverpopException("Mandatory column '{$c}' not found.");
      }
    }

    foreach ($this->mandatoryConfigColumns as $c)
    {
      $this->configNamesIndexes[$c] = array_search($c, $header);
    }

    while ($row = fgetcsv($fhIn)) 
    {
      $this->config[] = array(
        'username' => $row[$this->configNamesIndexes['username']],
        'password' => $row[$this->configNamesIndexes['password']],
        'engage_server' => $row[$this->configNamesIndexes['engage_server']],
        'date_from' => $row[$this->configNamesIndexes['date_from']],
        'date_to' => $row[$this->configNamesIndexes['date_to']],
        'sftp_port' => 22,
        'sftp_username' => $this->sanitizeUsername($row[$this->configNamesIndexes['username']]),
        'bucket' => $row[$this->configNamesIndexes['bucket']],
      );
    }

    if (count($this->config) <= 0)
    {
      throw new SilverpopException("No configuration found.");
    }
  }

  private function logMessage($message)
  {
    echo($message."\n");
  }

  public function run()
  {
    foreach ($this->config as $config)
    {
      $this->filesDownloaded = array();
      $this->downloadMetrics($config);

      foreach ($this->filesDownloaded as $f)
      {
        $this->extractAndLoad($f, $config['bucket']);
      }
    }
  }

  private function sanitizeUsername($username)
  {
    return str_replace('@', '%40', $username);
  }

  private function downloadMetrics($config)
  {
    // Initialize the library
    $silverpop = new EngagePod(array(
      'username'       => $config['username'],
      'password'       => $config['password'],
      'engage_server'  => $config['engage_server'],
    ));

    // Fetch all mailings for an Organization
    $mailings = $silverpop->getSentMailingsForOrg($config['date_from'], $config['date_to']);
    $this->logMessage('Downloading '.count($mailings).' mailings.');

    foreach ($mailings as $m)
    {
      $this->logMessage('Creating job for mailing '.$m['MailingId']);

      $result = $silverpop->trackingMetricExport($m['MailingId'], $config['date_from'], $config['date_to']);
      $file = $result['FILE_PATH'];
      $this->filesDownloaded[] = $file;

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

      $this->logMessage('Job completed for mailing '.$m['MailingId']);

      // ================== Download data from SFTP ==================
      if (!function_exists("ssh2_connect")){
        throw new SilverpopException('Function ssh2_connect not found, you cannot use ssh2 here');
      }

      if (!$connection = ssh2_connect('transfer'.$config['engage_server'].'.silverpop.com', $config['sftp_port'])){
        throw new SilverpopException('Unable to connect');
      }

      if (!ssh2_auth_password($connection, $config['username'], $config['password'])){
        throw new SilverpopException('Unable to authenticate.');
      }

      if (!$stream = ssh2_sftp($connection)){
        throw new SilverpopException('Unable to create a stream.');
      }

      if (!$dir = opendir("ssh2.sftp://{$stream}/{$this->remoteDir}")){
        throw new SilverpopException('Could not open the directory');
      }

      if (!$remote = @fopen("ssh2.sftp://{$stream}/{$this->remoteDir}{$file}", 'r'))
      {
        throw new SilverpopException("Unable to open remote file: $file");
      }

      if (!$local = @fopen($this->localDir . $file, 'w'))
      {
        throw new SilverpopException("Unable to create local file: $file");
      }

      fclose($local);
      fclose($remote);

      $data = file_get_contents("ssh2.sftp://{$stream}/{$this->remoteDir}{$file}");
      file_put_contents($this->localDir . $file, $data);

      $this->logMessage('Data downloaded for mailing '.$m['MailingId']);
    }
  }

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
      $fileName = $bucket.'.'.$fileName;

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
    
    $this->logMessage('Data extracted and loaded from file '.$zipFolder);
  }
}