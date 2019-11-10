(function($) {
  window.RedirectIndex = Garnish.Base.extend({
    $menu: null,
    $form: null,

    /**
     * The constructor.
     */
    init: function() {
      var $menuBtn = $('.sitemenubtn:first').menubtn().data('menubtn');

      if ($menuBtn !== undefined) {
        var $siteMenu = $menuBtn.menu;
        // Change the siteId when on hidden values
        $siteMenu.on('optionselect', function(ev) {
          var uri = '';
          for (var i = 0; i < Craft.sites.length; i++) {
            if (Craft.sites[i].id == Craft.elementIndex.siteId) {
              uri += 'sprout-redirects/redirects/new/' + Craft.sites[i].handle;
              uri = Craft.getUrl(uri);
              $("#sprout-base-redirects-new-button").attr("href", uri);
            }
          }
        });
      }
    },
  });

})(jQuery);
