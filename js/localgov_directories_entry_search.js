/**
 * @file
 *   Manages channel search boxes on entry nodes.
 */

(function localgovDirectoriesSearchScript(drupalSettings) {
  Drupal.behaviors.localgovDirectoriesSearch = {
    attach(context) {
      // Build a select list with options from all the search boxes.
      const formIds = Object.keys(
        drupalSettings.localgovDirectories.directoriesSearch,
      );
      const channelsDropdown = document.createElement('select');
      if (formIds.length > 1) {
        Object.keys(
          drupalSettings.localgovDirectories.directoriesSearch,
        ).forEach((formId) => {
          const channel = document.createElement('option');
          channel.value = formId;
          channel.text = Drupal.checkPlain(
            drupalSettings.localgovDirectories.directoriesSearch[formId],
          );
          channelsDropdown.appendChild(channel);
        });
        // Swap the select list into the title.
        Object.keys(
          drupalSettings.localgovDirectories.directoriesSearch,
        ).forEach((formId) => {
          const label = document.getElementById(`${formId}--channel`);
          label.innerHTML = channelsDropdown.outerHTML;
          label.childNodes[0].value = formId;
          // With an event that hides the current, unhides the selected,
          // and keeps the value of the selectors correct for the search
          // they are on.
          label.childNodes[0].addEventListener('change', () => {
            const previousId = label.id.slice(0, -9);
            const previous = document.getElementById(previousId);
            previous.style.display = 'none';
            const selected = document.getElementById(label.childNodes[0].value);
            selected.style.display = 'block';
            label.childNodes[0].value = previousId;
          });
        });
      }

      // Add a back to search results page if the referrer is the same path minus the last /page.
      if (
        document.URL.substr(0, document.URL.lastIndexOf('/')) ===
        document.referrer.split('?')[0]
      ) {
        const searchForm = document.querySelector(
          '#block-localgov-directories-channel-search-block',
        );
        const returnLink = document.createElement('a');
        returnLink.href = document.referrer;
        returnLink.innerText = Drupal.t('Back to search results');
        once('directory-return-link', searchForm).forEach((form) => {
          form.insertBefore(returnLink, form.firstChild);
        });
      }
    },
  };
})(drupalSettings, once);
