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
To set up everything for developing and testing. This is optional.
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

## To test (after installing module)
First create a field within the __Islandora Object__ content type and point it to the "identifier" taxonomy. The machine name isn't important, the script will automatically process it by "field type". Later in the config you can set the "prefix" if you want like "field_identifier".

### Go to Content Types > Repository Item > Manage Fields > [Add Field](https://islandora.traefik.me/admin/structure/types/manage/islandora_object/fields/add-field)
- Add a new field
  - Typed Label Text (plain)
- Label
  - Give it a name and it will output a machine name for you.
  - Example: "Fakers Identifier Types" will output "field_fakers_identifier_types"
- Save
-  Allowed number of values = Unlimited
-  Save Field Settings
-  Type: Reference Settings =  Identifier
-  Save

### Go to a repository object and enter data into the newly created field.
- Click on any node that is an Islandora Object > [edit](https://islandora.traefik.me/node/15/edit)
- Scroll to the bottom to the "Fakers Identifier Types" section.
  - Select a Type from the dropdown.
  - Give it a Label (optional)
  - Give it a Value
- Click Save

### Enable/configure Solr processor then compile fields
The first part of these instructions are to fix a ISLE-dc issue with the "Render Item" config. This will prevent an error from repetitively coming up. Then enable the processor and set the "prefix" (optional).

- Configuration > Search and metadata > Search API > Default Solr content index > [Fields](https://islandora.traefik.me/admin/config/search/search-api/index/default_solr_index/fields)
- Click edit for "Rendered item" in the "General" section
- Set "View mode for Content » Repository Item" to "Search Index" 
- Save
- Click [Processors](https://islandora.traefik.me/admin/config/search/search-api/index/default_solr_index/processors)
  - Check "Index fake fields"
  - Scroll to the bottom and and click "Index Fake Fields" tab in the "Processor settings" section
  - Add the machine name of the input field created earlier, example of "field_fakers_identifier_types"
  - Leave "Fake fields prefix" blank for now. The other 2 fields are currently being used for debugging purposes and are reset when the compile button is clicked.
  - Click Save if you added a "prefix"
  - Now click "Compile Solr Field Names"
  - The "Machine names" list should be the fields created within the "Islandora Object". Again these names will mean little. This list is to show it found them.
  - The last section "List of Solr field names generated" is the important list. This shows the new field name Solr will use. It should be the following format
    - Prefix + '_' + sanitized identifier type machine name + sanitized label(s) entered in the node(s)
- Click on the "[Fields](https://islandora.traefik.me/admin/config/search/search-api/index/default_solr_index/fields)" tab
  - Fields should now be in the list (Working on automating this step)
- Go back to the "[View](https://islandora.traefik.me/admin/config/search/search-api/index/default_solr_index)" tab
  - Click "Queue all items for reindexing"
  - Confirm
  - Index Now

Working on automating the trigger to compile "Compile Solr Field Names" and the process of enabling them on the Solr index field list. All of this and simplifying the code.