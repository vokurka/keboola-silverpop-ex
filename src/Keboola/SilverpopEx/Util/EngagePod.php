<?php

class EngagePod {

    /**
     * Current version of the library
     *
     * Uses semantic versioning (http://semver.org/)
     *
     * @const string VERSION
     */
    const VERSION = '0.0.2';

    private $_baseUrl;
    private $_session_encoding;
    private $_jsessionid;
    private $_username;
    private $_password;
    private $_debug = false;

    /**
     * Constructor
     *
     * Sets $this->_baseUrl based on the engage server specified in config
     */
    public function __construct($config) {

        // It would be a good thing to cache the jsessionid somewhere and reuse it across multiple requests
        // otherwise we are authenticating to the server once for every request
        $this->_baseUrl = 'https://api' . $config['engage_server'] . '.silverpop.com/XMLAPI';
        $this->_login($config['username'], $config['password']);
    }

    public function setDebug($debug)
    {
        $this->_debug = $debug;
    }

    /**
     * Fetches the contents of a list
     *
     * $listType can be one of:
     *
     * 0 - Databases
     * 1 - Queries
     * 2 - Both Databases and Queries
     * 5 - Test Lists
     * 6 - Seed Lists
     * 13 - Suppression Lists
     * 15 - Relational Tables
     * 18 - Contact Lists
     *
     */
    public function getLists($listType = 2, $isPrivate = true, $folder = null) {
        $data["Envelope"] = array(
            "Body" => array(
                "GetLists" => array(
                    "VISIBILITY" => ($isPrivate ? '0' : '1'),
                    "FOLDER_ID" => $folder,
                    "LIST_TYPE" => $listType,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['LIST']))
                return $result['LIST'];
            else {
                return array(); //?
            }
        } else {
            throw new \Exception("GetLists Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Fetches the aggregate tracking metrics for a mailing
     */
    public function getAggregateTrackingForMailing($mailingId, $reportId) {
        $data["Envelope"] = array(
            "Body" => array(
                "GetAggregateTrackingForMailing" => array(
                    "MAILING_ID" => $mailingId,
                    "REPORT_ID" => $reportId,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['Mailing']))
                return $result['Mailing'];
            else {
                return array(); //?
            }
        } else {
            throw new \Exception("getAggregateTrackingForMailing Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Fetches the mailings for organization
     */
    public function getSentMailingsForOrg($dateFrom, $dateTo) {
        $data["Envelope"] = array(
            "Body" => array(
                "GetSentMailingsForOrg" => array(
                    "DATE_START" => $dateFrom,
                    "DATE_END" => $dateTo,
                    "SHARED" => 1,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['Mailing']))
                return $result['Mailing'];
            else {
                return array(); //?
            }
        } else {
            throw new \Exception("GetSentMailingsForOrg Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Fetches the mailings for user
     */
    public function getSentMailingsForUser($dateFrom, $dateTo) {
        $data["Envelope"] = array(
            "Body" => array(
                "GetSentMailingsForUser" => array(
                    "DATE_START" => $dateFrom,
                    "DATE_END" => $dateTo,
                    "SHARED" => 1,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['Mailing']))
                return $result['Mailing'];
            else {
                return array(); //?
            }
        } else {
            throw new \Exception("GetSentMailingsForUser Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Creates job for data extract
     */
    public function trackingMetricExport($mailingId, $dateFrom, $dateTo) {
        $data["Envelope"] = array(
            "Body" => array(
                "TrackingMetricExport" => array(
                    "MAILING_ID" => $mailingId,
                    "SEND_DATE_START" => $dateFrom,
                    "SEND_DATE_END" => $dateTo,
                    "MOVE_TO_FTP" => 1,
                    "ALL_AGGREGATE_METRICS" => 1,
                    "AGGREGATE_SUMMARY" => 1,
                    "AGGREGATE_CLICKS" => 1,
                    "AGGREGATE_CLICKSTREAMS" => 1,
                    "AGGREGATE_CONVERSIONS" => 1,
                    "AGGREGATE_ATTACHMENTS" => 1,
                    "AGGREGATE_MEDIA" => 1,
                    "AGGREGATE_SUPPRESSIONS" => 1,
                    "TOP_DOMAINS" => 1,
                    "MAIL_TRACK_INTERVAL" => 1,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['MAILING']))
                return $result['MAILING'];
            else {
                return array(); //?
            }
        } else {
            throw new \Exception("TrackingMetricExport Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Creates job for data extract for contact list
     */
    public function exportList($listId, $dateFrom, $dateTo, $format='CSV') {
        $data["Envelope"] = array(
            "Body" => array(
                "ExportList" => array(
                    "LIST_ID" => $listId,
                    "DATE_START" => $dateFrom,
                    "DATE_END" => $dateTo,
                    "EXPORT_TYPE" => "ALL",
                    "EXPORT_FORMAT" => "CSV",
                    "FILE_ENCODING" => "UTF-8",
                    "LIST_DATE_FORMAT" => "yyyy-MM-dd",
                    "INCLUDE_LEAD_SOURCE" => true,
                    "INCLUDE_RECIPIENT_ID" => true,
                    "EXPORT_FORMAT" => $format,
                    //"MOVE_TO_FTP" => 1,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            return $result;
        } else {
            throw new \Exception("ExportList Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Creates job for data extract about events
     */
    public function rawRecipientDataExport($listId, $dateFrom, $dateTo, $format='CSV', $eventParam = array()) {
        $formatCode = 0;
        if ($format == 'PIPE')
        {
            $formatCode = 1;
        }
        else if ($format == 'TAB')
        {
            $formatCode = 2;
        }

        $defaultParam = array(
                    "LIST_ID" => $listId,
                    "EVENT_DATE_START" => $dateFrom,
                    "EVENT_DATE_END" => $dateTo,
                    "EXPORT_FORMAT" => 0,
                    "INCLUDE_CHILDREN" => 1,
                    "SHARED" => 1,
                    "SENT_MAILINGS" => 1,
                    "ALL_EVENT_TYPES" => 1,
                    "RETURN_SUBJECT" => 1,
                    "RETURN_MAILING_NAME" => 1,
                    "MOVE_TO_FTP" => 1,
                    "EXPORT_FORMAT" => $formatCode,
                );
        $eventParam = array_merge($defaultParam, $eventParam);

        $data["Envelope"] = array(
            "Body" => array(
                "RawRecipientDataExport" => $eventParam,
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            if (isset($result['MAILING']))
                return $result['MAILING'];
            else {
                return array(); //?
            }
        } else {
            throw new \Exception("RawRecipientDataExport Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Calculate a query
     *
     */
    public function calculateQuery($databaseID) {
        $data["Envelope"] = array(
            "Body" => array(
                "CalculateQuery" => array(
                    "QUERY_ID" => $databaseID,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            return $result["JOB_ID"];
        } else {
            throw new \Exception("Silverpop says: ".$response["Envelope"]["Body"]["Fault"]["FaultString"]);
        }
    }

    /**
     * Get the meta information for a list
     *
     */
    public function getListMetaData($databaseID) {
        $data["Envelope"] = array(
            "Body" => array(
                "GetListMetaData" => array(
                    "LIST_ID" => $databaseID,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            return $result;
        } else {
            throw new \Exception("Silverpop says: ".$response["Envelope"]["Body"]["Fault"]["FaultString"]);
        }
    }

    /**
     * Create a new query
     *
     * Takes a list of criteria and creates a query from them
     *
     * @param string $queryName The name of the new query
     * @param int    $parentListId List that this query is derived from
     * @param        $parentFolderId
     * @param        $condition
     * @param bool   $isPrivate
     *
     * @throws \Exception
     * @internal param string $columnName Column that the expression will run against
     * @internal param string $operators Operator that will be used for the expression
     * @internal param string $values
     * @return int ListID of the query that was created
     */
    public function createQuery($queryName, $parentListId, $parentFolderId, $condition, $isPrivate = true) {
        $data['Envelope'] = array(
            'Body' => array(
                'CreateQuery' => array(
                    'QUERY_NAME' => $queryName,
                    'PARENT_LIST_ID' => $parentListId,
                    'PARENT_FOLDER_ID' => $parentFolderId,
                    'VISIBILITY' => ($isPrivate ? '0' : '1'),
                    'CRITERIA' => array(
                        'TYPE' => 'editable',
                        'EXPRESSION' => $condition,
                    ),
                ),
            ),
        );

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];

        if ($this->_isSuccess($result)) {
            if (isset($result['ListId']))
                return $result['ListId'];
            else {
                throw new \Exception('Query created but no query ID was returned from the server.');
            }
        } else {
            throw new \Exception("createQuery Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Get a data job status
     *
     * Returns the status or throws an exception
     *
     */
    public function getJobStatus($jobId) {

        $data["Envelope"] = array(
            "Body" => array(
                "GetJobStatus" => array(
                    "JOB_ID" => $jobId
                ),
            ),
        );

        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];

        if ($this->_isSuccess($result)) {
            if (isset($result['JOB_STATUS']))
                return $result;
            else {
                throw new Exception('Job status query was successful but no status was found.');
            }
        } else {
            throw new \Exception("getJobStatus Error: ".$this->_getErrorFromResponse($response));
        }

    }

    /**
     * Private method: authenticate with Silverpop
     *
     */
    private function _login($username, $password) {
        $data["Envelope"] = array(
            "Body" => array(
                "Login" => array(
                    "USERNAME" => $username,
                    "PASSWORD" => $password,
                ),
            ),
        );
        $response = $this->_request($data);
        $result = $response["Envelope"]["Body"]["RESULT"];
        if ($this->_isSuccess($result)) {
            $this->_jsessionid = $result['SESSIONID'];
            $this->_session_encoding = $result['SESSION_ENCODING'];
            $this->_username = $username;
            $this->_password = $password;
        } else {
            throw new \Exception("Login Error: ".$this->_getErrorFromResponse($response));
        }
    }

    /**
     * Private method: generate the full request url
     *
     */
    private function _getFullUrl() {
        return $this->_baseUrl . (isset($this->_session_encoding) ? $this->_session_encoding : '');
    }

    /**
     * Private method: make the request
     *
     */
    private function _request($data, $replace = array(), $attribs = array()) {

        if (is_array($data))
        {
            $atx = new ArrayToXML($data, $replace, $attribs);;
            $xml = $atx->getXML();
        }
        else
        {
            //assume raw xml otherwise, we need this because we have to build
            //  our own sometimes because assoc arrays don't support same name keys
            $xml = $data;
        }

        $fields = array(
            "jsessionid" => isset($this->_jsessionid) ? $this->_jsessionid : '',
            "xml" => $xml,
        );

        if ($this->_debug === true)
        {
            echo "Request: ";
            print_r($xml);    
        }

        $counter = 1;
        $response = $this->_httpPost($fields);

        do
        {
            if ($this->_debug === true)
            {
                echo "Response: ";
                print_r($response);    
            }

            $arr = xml2array($response);
            
            if (isset($arr["Envelope"]["Body"]["RESULT"]["SUCCESS"])) 
            {
                return $arr;
            } 
            else 
            {
                print_r($arr);
                throw new \Exception("HTTP Error: Invalid data from the server");
            }

            $response = $this->_httpPost($fields);
            $counter++;
        }
        while ($counter <= 5);

        throw new \Exception("HTTP request failed");
    }

    /**
     * Private method: post the request to the url
     *
     */
    private function _httpPost($fields) {
        $fields_string = http_build_query($fields);
        //open connection
        $ch = curl_init();

        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_HTTPHEADER, array('Expect:'));
        curl_setopt($ch,CURLOPT_URL,$this->_getFullUrl());
        curl_setopt($ch,CURLOPT_POST,count($fields));
        curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

        //execute post
        $result = curl_exec($ch);

        //close connection
        curl_close($ch);

        return $result;
    }

    /**
     * Private method: parse an error response from Silverpop
     *
     */
    private function _getErrorFromResponse($response) {
        if (isset($response['Envelope']['Body']['Fault']['FaultString']) && !empty($response['Envelope']['Body']['Fault']['FaultString'])) {
            return $response['Envelope']['Body']['Fault']['FaultString'];
        }
        return 'Unknown Server Error';
    }

    /**
     * Private method: determine whether a request was successful
     *
     */
    private function _isSuccess($result) {
        if (isset($result['SUCCESS']) && in_array(strtolower($result["SUCCESS"]), array('true', 'success'))) {
            return true;
        }
        return false;
    }

}

// Function for mapping
function removeQuotes($string)
{
    return str_replace('"', '', $string);
}
