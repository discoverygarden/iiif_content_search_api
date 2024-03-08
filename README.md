# IIIF Content Search API

## Introduction

[IIIF Content Search API](https://iiif.io/api/search/) [V1](https://iiif.io/api/search/1.0/) and [V2](https://iiif.io/api/search/2.0/)  implementations. Integrates with [the IIIF Presentation API module](https://github.com/discoverygarden/iiif_presentation_api/) to
provide search and highlighting functionality.

## Requirements

This module requires the following modules/libraries:

* [Search API](https://www.drupal.org/project/search_api)

Suggested (at least to reference) are:

* [Islandora HOCR](https://github.com/discoverygarden/islandora_hocr)
* [Search API Solr](https://www.drupal.org/project/search_api_solr)

## Configuration

Configuration is presented performed via environment variables.

| Variable | Default | Description                                                                                                                                                        |
| --- | --- |--------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `IIIF_CONTENT_SEARCH_INDEX_ID` | `default_solr_index` | The index in which to search.                                                                                                                                      |
| `IIIF_CONTENT_SEARCH_HIGHLIGHTING_FIELD` | `islandora_hocr_field` | The field of the index in which to attempt to perform highlighting.                                                                                                |
| `IIIF_CONTENT_SEARCH_ANCESTOR_FIELD` | `field_ancestors` | The field of the index to filter using the ID relative to which the given query is to be performed, for searching with structured content (such as paged content). |
| `IIIF_CONTENT_SEARCH_DOC_ID_FIELD` | `nid` | Field of the index to search for the item proper, should it contain any highlight response. (Only relevant when searching on a particular page/image)              |

## Installation

Install as usual, see
[this]( https://www.drupal.org/docs/extending-drupal/installing-modules) for
further information.

## Troubleshooting/Issues

Having problems or solved a problem? Contact [discoverygarden](http://support.discoverygarden.ca).

## Maintainers/Sponsors

This project has been sponsored by:

* [discoverygarden](http://wwww.discoverygarden.ca)

Sponsor:

* [CTDA: Connecticut Digital Archive](https://lib.uconn.edu/find/connecticut-digital-archive/)

## Development

If you would like to contribute to this module, please check out our helpful
[Documentation for Developers](https://github.com/Islandora/islandora/wiki#wiki-documentation-for-developers)
info, [Developers](http://islandora.ca/developers) section on Islandora.ca and
contact [discoverygarden](http://support.discoverygarden.ca).

## License

[GPLv3](http://www.gnu.org/licenses/gpl-3.0.txt)
