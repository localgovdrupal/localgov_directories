Drupal.behaviors.localgovDirectoriesMaps = {
  attach: function (context, settings) {
    context = context || document;
    const maps = context.querySelectorAll('form .geofield-map-widget');

    maps.forEach(map => {
      const mapParentId = map.closest('.field-group-tab').getAttribute('id');
      console.log(mapParentId);
      const correspondingLink = context.querySelector(`[href="#${mapParentId}"]`);
      correspondingLink.addEventListener('click', () => {
        window.dispatchEvent(new Event('resize'));
      });
    });

  }
};
