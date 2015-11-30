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
    '#password', 
    'engage_server', 
    'date_from', 
    'date_to',
    'export_aggregated_reports',
    'export_contact_lists',
    'export_events',
    'lists_to_download',
  );
  private $remoteDir = 'download/';
  private $localDir = '/tmp/';
  private $destinationFolder;

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

    if (isset($ymlConfig['columns_in_contact_lists']) && !empty($ymlConfig['columns_in_contact_lists']))
    {
      $this->config['columns_in_contact_lists'] = $ymlConfig['columns_in_contact_lists'];
    }

    if (!empty($ymlConfig['debug']))
    {
      $this->config['debug'] = true;
    }

    // print_r($this->config);
    // exit;
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
      'password'       => $this->config['#password'],
      'engage_server'  => $this->config['engage_server'],
    ));

    if (!empty($this->config['debug']) && $this->config['debug'] === true)
    {
      $silverpop->setDebug(true);
    }

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

    foreach ($this->config['lists_to_download'] as $listName => $list)
    {
      $result = $silverpop->exportList($list, $this->config['date_from'], $this->config['date_to']);

      $this->downloadJob($result, $silverpop, 'contact_lists', $listName.'","'.$list);
    }

    $this->logMessage('Download completed for contact lists.');
  }

  private function exportEvents($silverpop)
  {
    $this->logMessage('Downloading events.');

    foreach ($this->config['lists_to_download'] as $listName => $list)
    {
      $result = $silverpop->rawRecipientDataExport($list, $this->config['date_from'], $this->config['date_to']);

      $this->downloadJob($result, $silverpop, 'events', $list);
    }

    $this->logMessage('Download completed for events.');
  }

  private function downloadJob($result, $silverpop, $type, $listId='')
  {
    if (empty($result['JOB_ID']))
    {
      $this->logMessage("WARNING: Last job was not successfully created. Check the source in Silverpop.");
      return;
    }

    $file = str_replace('/download/', '', $result['FILE_PATH']);

    // Wait till its done
    $counter = 0;
    do
    {
    	sleep(2);

    	$status = $silverpop->getJobStatus($result['JOB_ID']);
      $counter++;
    } while (!in_array($status['JOB_STATUS'], array('COMPLETE', 'CANCELLED', 'ERROR')) && $counter < 1800);

    // Check if everything happend OK
    if ($status['JOB_STATUS'] != 'COMPLETE')
    {
      throw new SilverpopException('An error occured while creating report in Silverpop. Last job status: '.$status['JOB_STATUS'].'. Last job status response: '.json_encode($status));
    }

    $this->logMessage('Job finished for ID '.$result['JOB_ID']);

    // ================== Download data from SFTP ==================
    if (!function_exists("ssh2_connect")){
      throw new SilverpopException('Function ssh2_connect not found, you cannot use ssh2 here');
    }

    if (!$connection = ssh2_connect('transfer'.$this->config['engage_server'].'.silverpop.com', $this->config['sftp_port'])){
      throw new SilverpopException('Unable to connect');
    }

    if (!ssh2_auth_password($connection, $this->config['username'], $this->config['#password'])){
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

    if ($type == 'contact_lists')
    {
      $this->loadFile($this->localDir.$file, $this->config['bucket'], $type, true, 'LIST_NAME","LIST_ID', $listId);
    }
    else if ($type == 'events')
    {
      $this->extractAndLoad($this->localDir.$file, $this->config['bucket'], array('Raw Recipient Data Export' => 'events'), 'LIST_NAME","LIST_ID', $listId);
    }
    else
    {
      $this->extractAndLoad($this->localDir.$file, $this->config['bucket']);
    }

    $this->logMessage('Data downloaded for job '.$result['JOB_ID']);
  }

  // Extracts file from zip and consolidates data into output file
  private function extractAndLoad($file, $bucket, $renames=array(), $headerPrefix='', $rowPrefix='')
  {
    $writeHeader = false;
    $zipFolder = str_replace('.zip', '', $file);
    $zip = new ZipArchive;
    $res = $zip->open($file);

    if ($res === TRUE) 
    {
      $zip->extractTo($this->localDir.$zipFolder);
      $zip->close();
    }
    else
    {
      throw new SilverpopException('Code: '.$res.' - Unable to unzip a file: '.$this->localDir.$file);
    }

    foreach (glob($this->localDir.$zipFolder.'/*') as $file)
    {
      $fileName = explode('/', $file);
      $fileName = $fileName[count($fileName)-1];
      $fileName = str_replace('.csv', '', $fileName);

      foreach ($renames as $match => $newName)
      {
        if (strpos($file, $match) !== false)
        {
          $fileName = $newName;
        }
      }

      $this->loadFile($file, $bucket, $fileName, $writeHeader, $headerPrefix, $rowPrefix);
    }
    
    $this->logMessage('Data extracted and loaded from file '.$zipFolder);
  }

  // loads CSV file and consolidates its data into destination file
  private function loadFile($file, $bucket, $destinationFile, $writeHeader, $headerPrefix='', $rowPrefix='')
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

    // Selecting the right columns for writing (now for contact_lists)
    $columnIndexes = array();

    if ($destinationFile == 'contact_lists' && !empty($this->config['columns_in_contact_lists']))
    {
      $newHeader = array();

      $explodedHeader = explode(',', $header);
      $explodedHeader = array_map("removeQuotes", $explodedHeader);

      // Getting indexes of concerned columns
      foreach ($explodedHeader as $index => $columnName)
      {
        if (in_array($columnName, $this->config['columns_in_contact_lists']))
        {
          $columnIndexes[$columnName] = $index;
        }
      }

      // Checking if we have everything defined
      if (count($columnIndexes) != count($this->config['columns_in_contact_lists']))
      {
        throw new SilverpopException("Could not find all the columns in the dataset liek defined in configuration.");
      }

      // Selecting all the columns according to definition
      foreach ($this->config['columns_in_contact_lists'] as $columnName)
      {
        if (!empty($explodedHeader[$columnIndexes[$columnName]]))
        {
          $newHeader[] = $explodedHeader[$columnIndexes[$columnName]];
        }
        else
        {
          throw new SilverpopException("Unable to locate defined column in contact list.");
        }
      }

      $header = '"'.implode('","', $newHeader)."\"\n";
    }

    // Analyzing header
    if ($writeHeader == true)
    {
      // Checking for headers longer than 64 characters (Storage API regulation)
      $headerParts = explode(',', $header);
      
      foreach ($headerParts as $index => $part)
      {
        if (strlen($part) > 64)
        {
          $headerParts[$index] = substr($part, 0, 62).'"';
        }
      }

      $header = implode(',', $headerParts);

      if (!empty($headerPrefix))
      {
        $header = '"'.$headerPrefix.'",'.$header;
      }

      // Finally writing headers
      fwrite($destination, $header);

      $writeHeader = false;
    }

    while ($row = fgets($source)) 
    {
      // Selecting defined columns by the template in columns_in_contact_lists
      if ($destinationFile == 'contact_lists' && !empty($this->config['columns_in_contact_lists']))
      {
        $newRow = array();

        $explodedRow = explode(',', $row);
        $explodedRow = array_map("removeQuotes", $explodedRow);

        foreach ($this->config['columns_in_contact_lists'] as $columnName)
        {
          if (!empty($explodedRow[$columnIndexes[$columnName]]))
          {
            $newRow[] = $explodedRow[$columnIndexes[$columnName]];
          }
          else
          {
            throw new SilverpopException("Unable to locate defined cell in contact list.");
          }
        }

        $row = '"'.implode('","', $newRow)."\"\n";
      }

      // Adding prefix to the row
      if (!empty($rowPrefix))
      {
        $row = '"'.$rowPrefix.'",'.$row;
      }

      // Actually writing the row
      fwrite($destination, $row);
    }

    fclose($source);
    fclose($destination);
  }

}