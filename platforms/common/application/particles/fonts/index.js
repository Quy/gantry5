"use strict";

var prime    = require('prime'),
    $        = require('../../utils/elements.moofx'),
    zen      = require('elements/zen'),
    storage  = require('prime/map')(),
    Emitter  = require('prime/emitter'),
    Bound    = require('prime-util/prime/bound'),
    Options  = require('prime-util/prime/options'),
    domready = require('elements/domready'),

    bind     = require('mout/function/bind'),
    map      = require('mout/array/map'),
    forEach  = require('mout/array/forEach'),
    contains  = require('mout/array/contains'),
    last     = require('mout/array/last'),
    merge    = require('mout/object/merge'),

    modal    = require('../../ui').modal,
    async    = require('async'),

    request  = require('agent');

var Fonts = new prime({

    mixin: Bound,

    inherits: Emitter,

    constructor: function() {
        this.data = null;
        this.field = null;
        this.element = null;
    },

    open: function(event, element, container) {
        if (!this.data || !this.field) { return this.getData(element); }

        var list = [];
        forEach(this.data, function(value) {
            list.push(value.family);
        });

        if (container) {
            container.empty().appendChild(this.buildLayout());
            return;
        }

        modal.open({
            content: 'Loading...',
            className: 'g5-dialog-theme-default g5-modal-fonts',
            afterOpen: bind(function(container) {
                console.log(container);
                setTimeout(bind(function(){
                    container.empty().appendChild(this.buildLayout());
                }, this), 1);
            }, this)
        });
    },

    getData: function(element) {
        var data = element.data('g5-fontpicker');
        if (!data) {
            throw new Error('No fontpicker data found');
        }

        data = JSON.parse(data);
        this.field = $(data.field);

        modal.open({
            content: 'Loading...',
            className: 'g5-dialog-theme-default g5-modal-fonts',
            remote: data.data,
            remoteLoaded: bind(function(response, instance) {
                if (response.error) {
                    instance.elements.content.html(response.body.html + '[' + data.data + ']');
                    return false;
                }

                this.data = response.body.items;

                this.open(null, element, instance.elements.content);
            }, this)
        });
    },

    buildLayout: function() {
        var html = zen('div#g-fonts.g-grid'),
            sidebar = zen('div.g-sidebar.g-block.size-1-4').bottom(html),
            main = zen('div.g-main.g-block').bottom(html),
            ul = zen('ul').bottom(sidebar),
            search = zen('div.settings-block').appendChild(zen('input[type="text"][placeholder="Search..."]')).top(sidebar);

        async.eachSeries(this.data, function(font, callback) {
            var variant = contains(font.variants, 'regular') ? '' : ':' + font.variants[0];
            zen('li').html(font.family).bottom(ul).style({ fontFamily: font.family });
            var style = zen('link[href="http://fonts.googleapis.com/css?family=' + font.family + variant + '"][type="text/css"][rel="stylesheet"]');
            //style.on('load', function(){ callback(); });
            //style.on('error', function(){ callback('Unable to load "'+font.family+'"'); });
            $('head').appendChild(style);

            callback();
        });

        return html;
    }
});

var FontsPicker = new Fonts();

domready(function() {
    $('body').delegate('click', '[data-g5-fontpicker]', bind(FontsPicker.open, FontsPicker));
});

module.exports = FontsPicker;