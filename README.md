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
  "engage_server": "<number of your engage server>",
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
  ],

  "debug": 1,

  "format": "PIPE",

  "csv_escape_character": "\""
}
```

First part describes credentials with correct Engage server to connect to. Also, you must specify bucket that serves as destination for storing the data.

*IMPORTANT NOTE:* User used for API must be Organization Admin.

*IMPORTANT NOTE2:* Number of engage server - just look at your login URL to the Silverpop (something like login6.silverpop.com) and take the number, put it into the configuration. Easy!

Second part describes date from and date to for downloading the data. It supports both classic DateTime formats as well as all the syntax you can use in PHP function (http://www.w3schools.com/php/func_date_strtotime.asp).

Third part tells the extractor what to download. 0 = do NOT download, 1 = download. You can turn on/off as the features as you wish. Note that the most time and resource consuming feature is (in paradox) aggregated_reports, because it must download report for each mailing you have in Silverpop. Be carefull.

If you enable events or contact list download, you must specify "lists_to_download". It has a name (you can choose whatever you like - it is used as list identifier in the column in result table) and ID from Silverpop. 

*IMPORTANT NOTE3:* You can find List IDs in Silverpop UI. Just go to Databases and hover your mouse cursor over the name of your database. A note saying LIST ID: 1234 will show up.

"columns_in_contact_lists" specifies columns in contact lists downloaded. It is optional, but recommended. When you define the contact lists to download, it is downloaded into one table. And a problem rises when you have different structure of the contact lists. When you specify columns that are present in ALL contact lists, it is properly merged together.

"debug" option is there for debugging problems with Silverpop API. It will enable logging of ALL requests and response that goes to and from Silverpop API. This option produce a HUGE amount of log data, so please remember to turn it off in production. It is supposed to be only for debugging problems.

"format" is an option for downloading data about contacts and events with different delimiter (it affects only delimiter for transport between Silverpop API and Docker container - KBC Storage will get standard CSV). This is here because Silverpop has error in their CSV generating function - when there is comma in mailing subject, it produces invalid CSV. Possible values for this option are "CSV", "PIPE" and "TAB" (pretty self-explaining). Default is CSV, so if you do not encounter problem with commas, you do not have to include this option in configuration at all.

"csv_escape_character" is option for setting, which character should be used for parsing and writing CSV files. PHP has strangly set up default of this character to the backslash, which is not entirely RFC compliant. So, if you find yourself in situation where you have for example backslash as last character in your data before quote, it can mess up the whole CSV. Should you be in this situation, just change this escape character to quote and it should work like expected.

In case of questions or problems, contact directly vokurka@keboola.com.