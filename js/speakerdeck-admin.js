(function($){
var validateUrl = function (str) {
        var pattern = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/; // fragment locater
        if(!pattern.test(str)) {
            return false;
        } else {
            return true;
        }
    };

    $(document).ready(function(){
        var $urlfield = $('#speakerdeck-field-url'),
            $enablefields = $('#speakerdeck-enable-fields');

        $enablefields.on('click', function (e){
            e.preventDefault();
            $('#speakerdeck-field-id').prop('disabled',false);
            $('#speakerdeck-field-ratio').prop('disabled',false);
            $('#speakerdeck-field-download').prop('disabled',false);
            $('#speakerdeck-field-total').prop('disabled',false);
            return;
        });
        $urlfield.on({
            'change': function(e){
                var $this = $(this),
                    url = $this.val();
                if (!validateUrl(url))
                    return;
                if (url.split("/")[2]!=='speakerdeck.com' && url.split("/")[2]!=='www.speakerdeck.com')
                    return;
                
                var reg = /.+?\:\/\/.+?(\/.+?)(?:#|\?|$)/,
                    match = reg.exec( url );
                if (match === null)
                    return;
                var search = match[1].substring(1).split('/')[0],
                    data = {
                        action: 'speakerdeck_webcrawler',
                        url: url
                    };
                $.post(ajaxurl, data, function(response) {
                    var $response = $(response).filter('#content'),
                        $talk = $response.find('#talk'),
                        $main = $talk.find('.main'),
                        $embed = $main.find('#slides_container').children('.speakerdeck-embed'),
                        slide = {
                            id: $embed.attr('data-id'),
                            title: $main.find('h1').text(),
                            ratio: $embed.attr('data-ratio'),
                            download: $talk.find('#share_pdf').attr('href')
                        };
                    data.url = 'https://speakerdeck.com/search?q=' + encodeURIComponent([search, " ", slide.title].join(''))
                    $.post(ajaxurl, data, function(response) {
                        var $response = $(response).filter('#content'),
                            $talk = $response.find(['.talk[data-id="', slide.id, '"]'].join(''));
                        slide.total = parseInt($talk.attr('data-slide-count'));
                        $('#speakerdeck-field-id').val(slide.id).prop('disabled',false);
                        $('#speakerdeck-field-title').val(slide.title).prop('disabled',false);
                        $('#speakerdeck-field-ratio').val(slide.ratio).prop('disabled',false);
                        $('#speakerdeck-field-download').val(slide.download).prop('disabled',false);
                        $('#speakerdeck-field-total').val(slide.total).prop('disabled',false);
                    });
                });
            },
            'keyup': function(){
                if ($urlfield.val()!='')
                    $urlfield.trigger('change')
            }
        });
        if ($urlfield.val()!=''){
            $urlfield.trigger('change')
        }
    });
})(jQuery);