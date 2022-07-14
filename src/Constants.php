<?php

declare(strict_types = 1);

namespace Drupal\localgov_directories;

/**
 * Constants for this module.
 */
class Constants {

  const CHANNEL_SELECTION_FIELD = 'localgov_directory_channels';

  const PROXIMITY_SEARCH_CFG_FIELD = 'localgov_proximity_search_cfg';

  const CHANNEL_SEARCH_BLOCK = 'localgov_directories_channel_search_block';

  const FACET_INDEXING_FIELD = 'localgov_directory_facets_filter';

  const FACET_SELECTION_FIELD = 'localgov_directory_facets_select';

  const FACET_CONFIG_ENTITY_ID = 'localgov_directories_facets';

  const FACET_TYPE_CONFIG_ENTITY_ID = 'localgov_directories_facets_type';

  const FACET_CONFIG_FILE = 'facets.facet.localgov_directories_facets';

  const CHANNEL_VIEW = 'localgov_directory_channel';

  const CHANNEL_VIEW_DISPLAY = 'node_embed';

  const CHANNEL_VIEW_PROXIMITY_SEARCH_DISPLAY = 'node_embed_for_proximity_search';

  const CHANNEL_NODE_BUNDLE = 'localgov_directory';

}
