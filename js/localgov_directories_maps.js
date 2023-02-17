Drupal.behaviors.localgovDirectoriesMaps = {
  attach: function (context, settings) {
    context = context || document;
    const maps = context.querySelectorAll("form .geofield-map-widget");

    maps.forEach((map) => {
      new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
          if (entry.intersectionRatio > 0) {
            window.dispatchEvent(new Event("resize"));
            observer.disconnect();
          }
        });
      }).observe(map);
    });
  },
};
