# TwingleForm API

The TwingleForm API is meant to offer a simple and secure interface to read and alter only a limited number of values
provided by the TwingleProject API.

It can be used by external CMS (like Drupal) to receive a list of TwingleProjects. The API delivers for example the
embed codes of all active TwingleProjects so that they can be integrated in a website.

It's also possible to update the TwingleProject `url` field via the TwingleForm Create API. In this case the `url`
fields of all TwingleCampaign children of the TwingleProject will be updated, too.

## Get & Getsingle API

*Note: The Getsingle API delivers only one result or returns an error.*

### API parameters

|parameter               |required|description                                           |example value|
|------------------------|:------:|------------------------------------------------------|-------------|
|**id**                  |no      |campaign id                                           |16           |
|**name**                |no      |name of the TwingleProject campaign                   |Donation_Form|
|**title**               |no      |title of the TwingleProject campaign                  |Donation Form|
|**twingle_project_type**|no      |project type can be *default*, *event* or *membership*|default      |

### Example Get call

```curl
curl --location -g --request GET 'http://dmaster.localhost:7979/sites/all/modules/civicrm/extern/rest.php?entity=TwingleForm&action=get&api_key=xxxxxxxxxxxxxxxxxxxx&key=xxxxxxxxxxxxxxxxxxxx&json={}'
```

### Response for successful Get call

```json
{
  "is_error": 0,
  "version": 3,
  "count": 2,
  "values": {
    "16": {
      "id": "16",
      "twingle_project_id": "3237",
      "title": "Donation Form",
      "name": "Donation_Form",
      "project_type": "default",
      "embed_code": "<!-- twingle --> ... <!-- twingle -->",
      "counter": "https://donationstatus.twingle.de/donation-status/xxxxxxxxxxxx"
    },
    "23": {
      "id": "23",
      "twingle_project_id": "3242",
      "title": "Another Donation Form",
      "name": "Donation_Form_Copy",
      "project_type": "event",
      "embed_code": "<!-- twingle --> ... <!-- twingle -->",
      "counter": "https://donationstatus.twingle.de/donation-status/xxxxxxxxxxxx"
    }
  }
}
```

### Example Getsingle call

```curl
curl --location -g --request GET 'http://dmaster.localhost:7979/sites/all/modules/civicrm/extern/rest.php?entity=TwingleForm&action=getsingle&api_key=xxxxxxxxxxxxxxxxxxxx&key=xxxxxxxxxxxxxxxxxxxx&json={%22id%22:16}' \
```

### Response for successful Getsingle call

```json
{
  "is_error": 0,
  "version": 3,
  "count": 7,
  "values": {
    "id": "16",
    "twingle_project_id": "3237",
    "title": "Donation Form",
    "name": "Donation_Form",
    "project_type": "default",
    "embed_code": "<!-- twingle --> ... <!-- twingle -->",
    "counter": "https://donationstatus.twingle.de/donation-status/xxxxxxxxxxxx"
  }
}
```

## Create API

### Create API parameters

|parameter|required|description                                   |example value                     |
|---------|:------:|----------------------------------------------|----------------------------------|
|**id**   |yes     |campaign id                                   |16                                |
|**url**  |yes     |url of the page where Twingle form is embedded|https://mywebsite.org/donationform|

### Example Create call

```curl
curl --location -g --request POST 'http://dmaster.localhost:7979/sites/all/modules/civicrm/extern/rest.php?entity=TwingleForm&action=create&api_key=xxxxxxxxxxxxxxxxxxxx&key=xxxxxxxxxxxxxxxxxxxx&json={%22id%22:%2016,%20%22url%22:%22https://mywebsite.org/donationform%22}'
```

### Response for successful Create call

```json
{
  "is_error": 0,
  "version": 3,
  "count": 5,
  "values": {
    "title": "Donation Form",
    "id": "16",
    "project_id": "3237",
    "project_type": "default",
    "status": "TwingleProject created"
  }
}
```

### Responses for failed Create calls

#### TwingleProject not found

```json
{
  "id": 999,
  "url": "https://mywebsite.org/donationform",
  "is_error": 1,
  "error_message": "Expected one TwingleProject but found 0"
}
```

#### Invalid URL

```json
{
  "id": 16,
  "url": "https://mywebsite.org/donation form",
  "is_error": 1,
  "error_message": "invalid URL"
}
```