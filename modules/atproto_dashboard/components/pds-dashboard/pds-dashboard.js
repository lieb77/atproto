(function (Drupal, once) {
  Drupal.behaviors.pdsDashboardTabs = {
    attach: function (context, settings) {
      // Look for the tab container and initialize the buttons
      const tabs = once('pds-tab-init', '.pds-dashboard .tab-link', context);
      
      tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
          // Update visual state
          const allTabs = e.target.closest('.tabs').querySelectorAll('.tab-link');
          allTabs.forEach(t => t.classList.remove('active'));
          e.target.classList.add('active');
        });
      });
    }
  };
})(Drupal, once);
