/**
 * @file
 * The map field doesn't load correctly when in a vertical tab.
 * See: https://github.com/localgovdrupal/localgov_directories/issues/87
 */

 (function (drupalSettings) {

  Drupal.behaviors.localgovDirectoriesMap = {
    attach: function attach(context, settings) {
      const verticalTabs = document.querySelectorAll('.vertical-tabs__menu-link');
      verticalTabs.forEach(verticalTab => {
        verticalTab.addEventListener('click', function() {
          window.dispatchEvent(new Event('resize'));
        })
      })
    }
  }

})(drupalSettings);
