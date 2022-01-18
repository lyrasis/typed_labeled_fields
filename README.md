## What is Typed labeled fields?
1. Adds the metadata functionality from ECS.
1. Automatically migrates the taxonomy terms for "identifiers".

### What are the goals for Typed labeled fields?
1st goal is to get a working example of the expected behavior with Identifiers.

### How does Typed labeled fields work?
This module sets up a field formatter, field validator, field type, and field widget for a list of fields. Then it will modify the content going into Solr fields.

## Installation
Instructions for install within [isle-dc](https://github.com/Islandora-Devops/isle-dc/) or see [## A full dev setup] below

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

### To enable (Solr plugin)
- Go to [https://islandora.traefik.me/admin/config/search/search-api/index/default_solr_index/processors](https://islandora.traefik.me/admin/config/search/search-api/index/default_solr_index/processors)
- Check "Enhanced Identifier Aggregated fields"
- Click Save

## What is the current status?
1. Transferred the metadata module from ECS over.
1. Transferred over some support logic found in support modules found in ECS into the https://github.com/lyrasis/typed_labeled_fields module
1. Added a migration of the identifiers (like ISBN) for testing and integrated it into the install process.
1. Ran tests to verify adding a field “typed Label….short” will output the same results as ECS both within Drupal and Solr.
1. Added a solr processor for the ability to push into Solr without having to create Drupal fields to store modified keys & values to accommodate the requirements point to in https://github.com/lyrasis/islandora-metadata/blob/main/field_types/all_typed_and_labeled_fields.adoc#indexing  --__Work in progress__

## What is still needed?
1. Using isle-dc what needs to be done?
    A. Processing invalid identifiers into valid identifiers
        - [Solr Prefix]_[Drupal field name][Drupal field name’s type][Drupal field name’s label]
1. is_ascii and case_sensitive was a customization on ECS that needs to be ported to avoid the constant notices.
1. Address the "The field Rendered item (rendered_item) on index Default Solr content index is missing view mode configuration for some datasources or bundles. Please review (and re-save) the field settings" error message.

## References
..

## A full dev setup
To set up everything for developing and testing.
```shell
make up
docker-compose exec -T drupal with-contenv bash -lc "git clone https://github.com/lyrasis/typed_labeled_fields/"
docker-compose exec -T drupal with-contenv bash -lc "composer require drupal/console:~1.0 --prefer-dist --optimize-autoloader -W -y"
docker-compose exec -T drupal with-contenv bash -lc "echo 'alias drupal=\"/var/www/drupal/vendor/drupal/console/bin/drupal\"' >> ~/.bashrc"
docker-compose exec -T drupal with-contenv bash -lc "composer require 'drupal/adminimal_theme:^1.6' --prefer-dist --optimize-autoloader -W"
docker-compose exec -T drupal with-contenv bash -lc "drush theme:enable adminimal_theme"
docker-compose exec -T drupal with-contenv bash -lc "drush config-set system.theme admin adminimal_theme -y"
docker-compose exec -T drupal with-contenv bash -lc "composer require 'drupal/adminimal_admin_toolbar:^1.11'"
docker-compose exec -T drupal with-contenv bash -lc "drush pm:enable -y adminimal_admin_toolbar"
docker-compose exec -T drupal with-contenv bash -lc "composer require 'drupal/config_delete:^1.17'"
docker-compose exec -T drupal with-contenv bash -lc "drush pm:enable -y config_delete"
docker-compose exec -T drupal with-contenv bash -lc "composer require drupal/console:~1.0 --prefer-dist --optimize-autoloader -W -y"
docker-compose exec -T drupal with-contenv bash -lc "drush en devel -y"
```