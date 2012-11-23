(function($){
    $.fn.speakerDeck = function(options) {
        var deck = {
            url: {
                s3: "https://speakerd.s3.amazonaws.com/presentations/",
                thumb: function(id, count){
                    return [this.s3, id, "/thumb_slide_", parseInt(count), ".jpg"].join('');
                },
                full: function(id, count){
                    return [this.s3, id, "/slide_", parseInt(count), ".jpg"].join('');
                },
            },
            html: {
                scrubber: "<div class='speakerdeck-scrubber'><div class='speakerdeck-scrubbed'></div></div>"
            }
        }
        return this.each(function (k, e) {
            var $this = $(e),
                data = $this.data('deck', $.parseJSON($this.attr('data-deck') || '')).data('deck'),
                $imgs = $();
            if (data==null)
                return;
            for (var i = data.total - 1; i >= 0; i--) {
                $.merge($imgs, $(["<img class='speakerdeck-timeline' src='", deck.url.thumb(data.id, i), "' />"].join('')));
            }
            $imgs.filter(':last').addClass('speakerdeck-visible');
            $this.append($(deck.html.scrubber)).prepend($imgs);
        }).on({
            'mousemove': function (e){
                var $this = $(this),
                    $scrubbed = $this.children('.speakerdeck-scrubber').children('.speakerdeck-scrubbed'),
                    data = $this.data('deck'),
                    offset = $this.offset(),
                    width = $this.width(),
                    relX = e.pageX - offset.left,
                    slide = null,
                    pos = null;

                if (relX>=(width-15)){
                    pos = 100;
                    slide = 0;
                }
                if (relX <= 15){
                    pos = 0;
                    slide = data.total-1;
                }
                relX = relX-15;
                width = width-30;
                if(slide===null && pos===null){
                    var percent = width/100,
                        slidePercent = data.total/100;
                    pos = Math.ceil(relX/percent);
                    slide = data.total - Math.floor(pos*slidePercent);
                }
                if (slide>=data.total)
                    slide = data.total-1;
                if (slide<0)
                    slide = 0;

                $this.children('.speakerdeck-timeline').filter('.speakerdeck-visible').removeClass('speakerdeck-visible').end().eq(slide).addClass('speakerdeck-visible');
                $scrubbed.css({'width': pos + "%"});
            },
            'mouseleave': function(){
                var $this = $(this),
                    $scrubbed = $this.children('.speakerdeck-scrubber').children('.speakerdeck-scrubbed'),
                    data = $this.data('deck'),
                    pos = 0;
                    slide = data.total-1;
                $this.children('.speakerdeck-timeline').filter('.speakerdeck-visible').removeClass('speakerdeck-visible').end().eq(slide).addClass('speakerdeck-visible');
                $scrubbed.css({'width': pos + "%"});
            }
        });
    }
    $(document).ready(function(){
        $('.speakerdeck-scrub').speakerDeck();
    });
})(jQuery);