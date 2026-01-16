**This was exploratory work toward making "new Islandora" usable for our client metadata. It was abandoned when we decided we could not support a hosting service for the new Islandora.**

Dynamically create Solr fields based on the Identifier Type + Label (optional). This can be expanded and automated but more work is needed.

## What is Typed labeled fields?
1. Adds some metadata functionality.
1. Automatically migrates the taxonomy terms for "identifiers".

### What are the goals for Typed labeled fields?
Imports identifiers to use to generate dynamic Solr document fields.

### How does Typed labeled fields work?
This module sets up a field formatter, field validator, field type, and field widget for a list of fields. Then it will modify the content going into Solr fields.

### TL;DR
Instructions for installing and running.
1. Install Module
1. Add Typed Labeled Text (plain) field type to an __Islandora_repository__ content type
1. Enable the Solr Processor
1. Add at least one 1 identifier to an existing Islandora object.
1. Click on checkbox to enable "Enhanced Identifier Aggregated fields(Fake Fields)" processor and click save
1. Go to vertical tab Enhanced Identifier Aggregated fields(Fake Fields)
1. Click "Discovery & Compile Solr field names"
1. Click on Fields > add the "Fake Field"
1. Click save & reindex

## Installation
Instructions for install within [isle-dc](https://github.com/Islandora-Devops/isle-dc/) or see [## A full dev setup] below

```shell
$ docker-compose exec drupal with-contenv bash

$ cd web/modules/contrib/

$ git clone https://github.com/lyrasis/typed_labeled_fields
$ drush pm:enable -y typed_labeled_fields
```

### To check if it's installed and running

Go to [/admin/help/typed_labeled_fields](/admin/help/typed_labeled_fields)
This loads the help page and should walk the user on how to set up the field in a way that's useful.

### To enable (Solr plugin)
- Go to [/admin/config/search/search-api/index/default_solr_index/processors](/admin/config/search/search-api/index/default_solr_index/processors)
- Check "Enhanced Identifier Aggregated fields"
- Click Save

## What is still needed?
Automate completely so the users doesn't need to occasionally add new values.

## How to use
For detailed instructions please visit the help page. 
Admin > Help > Typed Labeled Fields [/admin/help/typed_labeled_fields](/admin/help/typed_labeled_fields)

Also the instructions are found in code within the [.module](https://github.com/lyrasis/typed_labeled_fields/blob/master/typed_labeled_fields.module#L20) file.

## To Do
This really needs to be automated to be as useful as people are likely to expect it to be. Currently when new identifiers are filled in they are not dynamically added to the index. A CRON job or Action should be made to make this happen.
