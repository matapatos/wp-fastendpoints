For this scenario, we are going to create two JSON schemas: 1) for validating the request payload and another 2) to discard unwanted fields from responses (e.g. sensitive information).

### Request payload (create/update)

For the payload we decided to accept the following fields: 1) _post_title_, 2) _post_status_, 3) _post_type_ and 4) optionally _post_content_

```json
{
  "type": "object",
  "properties": {
    "post_title": {
      "type": "string"
    },
    "post_status": {
      "enum": ["publish", "draft", "private"],
      "default": "publish"
    },
    "post_type": {
      "const": "post"
    },
    "post_content": {
      "type": "string",
      "contentMediaType": "text/html"
    }
  },
  "required": ["post_title", "post_status", "post_type"]
}
```

### Response (retrieve)

For the response we decided to only return the following fields: 1) _post_title_ and 2) optionally _post_excerpt_

```json
{
  "type": "object",
  "properties": {
    "post_title": {
      "type": "string"
    },
    "post_excerpt": {
      "type": "string",
      "contentMediaType": "text/html"
    }
  },
  "required": ["post_title"]
}
```
