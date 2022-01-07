## What is Typed labeled fields?
..

### What are the goals for Typed labeled fields?
..

### How does Typed labeled fields work?
..

## Installation
Instructions for install within [isle-dc](https://github.com/Islandora-Devops/isle-dc/)

```shell
$ docker-compose exec drupal with-contenv bash

$ cd web/modules/contrib/

$ git clone https://github.com/lyrasis/typed_labeled_fields
$ drush pm:enable -y typed_labeled_fields

```

### To check if it's installed and running

Go to [https://islandora.traefik.me/admin/help/typed_labeled_fields](https://islandora.traefik.me/admin/help/typed_labeled_fields)
This page is loaded by the module.

### It should say the following 
```


About

Field types for Islandora for basic metadata

```

### To enable
- Go to [https://islandora.traefik.me/admin/config/search/search-api/index/default_solr_index/processors](https://islandora.traefik.me/admin/config/search/search-api/index/default_solr_index/processors)
- Check "Enhanced Identifier Aggregated fields"
- Click Save

## What is the current status?
..

## What is still needed?
..

## References
..