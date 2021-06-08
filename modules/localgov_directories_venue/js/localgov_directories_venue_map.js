/**
 * @file
 * The map field doesn't load correctly when in a vertical tab.
 * See: https://github.com/localgovdrupal/localgov_directories/issues/87
 */

(function (drupalSettings) {

  Drupal.behaviors.localgovDirectoriesMap = {
    attach: function attach(context, settings) {
      // On wide screens tabs are activated via links
      const verticalTabsLinks = document.querySelectorAll('.vertical-tabs__menu-link');
      // On narrow screens tabs are activated via summary element
      const verticalTabsSummaries = document.querySelectorAll('.vertical-tabs__item > summary');
      // Create an array of all the tabs.
      const verticalTabs = [...verticalTabsLinks, ...verticalTabsSummaries];
      verticalTabs.forEach(verticalTab => {
        verticalTab.addEventListener('click', function() {
          window.dispatchEvent(new Event('resize'));
        })
      })
    }
  }

})(drupalSettings);
