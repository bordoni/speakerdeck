(function($) {
    tinymce.create('tinymce.plugins.SpeakerDeck', {
        init : function(ed, url) {
            ed.addButton('speakerdeck', {
                title : 'speakerdeck.speak',
                image : url+'/../img/speakerdeck.png',
                onclick : function() {
                    var $info = $("#speakerdeck-tinymce-form");
                        $info.dialog({      
                            title: wpSpeakerDeckL10n.form_title,
                            width: 300,
                            height: 'auto',
                            modal: true,
                            dialogClass: 'wp-dialog',
                            zIndex: 300000,
                            autoOpen: false,
                            closeOnEscape: true,
                        }),
                        $items = $info.find('.speakerdeck-form-items').children('option');
                    $items.each(function(){
                        var $this = $(this);
                        $this.data('deck', $.parseJSON($this.attr('data-deck')));
                    });
                    $info.find('.speakerdeck-insert').on({
                        click: function(e){
                            var slide = $items.filter(':selected').data('deck');
                            tinymce.execCommand('mceInsertContent', false, ["[speakerdeck id='", slide.id, "']"].join(''));
                            $info.dialog('close');
                        }
                    });
                    $info.dialog('open');
                }
            });
        },
        createControl : function(n, cm) {
            return null;
        },
        getInfo : function() {
            return {
                longname : "Speaker Deck Shortcode",
                author : 'Gustavo Bordoni',
                authorurl : 'http://en.bordoni.me/',
                infourl : 'http://en.bordoni.me/plugins/speakerdeck',
                version : "1.0"
            };
        }
    });
    tinymce.PluginManager.add('speakerdeck', tinymce.plugins.SpeakerDeck);
})(jQuery);