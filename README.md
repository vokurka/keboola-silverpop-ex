# Documentation

Silverpop Extractor can download 3 kinds of data:

1. Aggregated metrics - list of reports that describes how recipients behave after receiving e-mail.
2. Contact lists - complete contact lists with all recipient data.
3. Event list - list of all events tied to recipients. Very useful for behavioral analytics.

Configuration looks like this:
```
{
  "username": "username@domain.com",
  "#password": "secret_password",
  "engage_server": "1",
  "bucket": "in.c-ex-silverpop-client_name",

  "date_from": "-14 days",
  "date_to": "today",

  "export_aggregated_reports": 0,
  "export_events": 1,
  "export_contact_lists": 1,

  "lists_to_download": {
    "name_of_list": "10920"
  },

  "columns_in_contact_lists": [
    "RECIPIENT_ID",
    "Email",
    "Opt In Date",
    "City",
    "State"
  ]
}
```

First part describes credentials with correct Engage server to connect to. Also, you must specify bucket that serves as destination for storing the data.

Second part describes date from and date to for downloading the data. It supports both classic DateTime formats as well as all the syntax you can use in PHP function (http://www.w3schools.com/php/func_date_strtotime.asp).

Third part tells the extractor what to download. 0 = do NOT download, 1 = download. You can turn on/off as the features as you wish. Note that the most time and resource consuming feature is (in paradox) aggregated_reports, because it must download report for each mailing you have in Silverpop. Be carefull.

If you enable events or contact list download, you must specify lists_to_download. It has a name (you can choose whatever you like - it is used as list identifier in the column in result table) and ID from Silverpop.

The last part specifies columns in contact lists downloaded. It is optional, but recommended. When you define the contact lists to download, it is downloaded into one table. And a problem rises when you have different structure of the contact lists. When you specify columns that are present in ALL contact lists, it is properly merged together.

In case of questions or problems, contact directly vokurka@keboola.com.