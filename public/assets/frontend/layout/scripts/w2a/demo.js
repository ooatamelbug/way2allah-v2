/*jslint  browser: true, white: true, plusplus: true */
/*global $, countries */

$(function () {
    'use strict';

   /* var authorsArray = $.map(authors_autocomplete_list, function (value, key) { return { value: value, data: key }; });
	var channelsArray = $.map(channels_autocomplete_list, function (value, key) { return { value: value, data: key }; });*/
	var popup_authorsArray = $.map(popup_authors_autocomplete_list, function (value, key) { return { value: value, data: key }; });
	var popup_channelsArray = $.map(popup_channels_autocomplete_list, function (value, key) { return { value: value, data: key }; });

    // Setup jQuery ajax mock:
    /*$.mockjax({
        url: '*',
        responseTime: 2000,
        response: function (settings) {
            var query = settings.data.query,
                queryLowerCase = query.toLowerCase(),
                re = new RegExp('\\b' + $.Autocomplete.utils.escapeRegExChars(queryLowerCase), 'gi'),
                suggestions = $.grep(countriesArray, function (country) {
                     // return country.value.toLowerCase().indexOf(queryLowerCase) === 0;
                    return re.test(country.value);
                }),
                response = {
                    query: query,
                    suggestions: suggestions
                };

            this.responseText = JSON.stringify(response);
        }
    });*/

    // Initialize ajax autocomplete:
   /* $('#autocomplete-ajax').autocomplete({
        // serviceUrl: '/autosuggest/service/url',
        lookup: countriesArray,
        lookupFilter: function(suggestion, originalQuery, queryLowerCase) {
            var re = new RegExp('\\b' + $.Autocomplete.utils.escapeRegExChars(queryLowerCase), 'gi');
            return re.test(suggestion.value);
        },
        onSelect: function(suggestion) {
            $('#selction-ajax').html('You selected: ' + suggestion.value + ', ' + suggestion.data);
        },
        onHint: function (hint) {
            $('#autocomplete-ajax-x').val(hint);
        },
        onInvalidateSelection: function() {
            $('#selction-ajax').html('You selected: none');
        }
    });*/

    // Initialize autocomplete with local lookup:
    // $('#kh_author_name').devbridgeAutocomplete({
    //     lookup: popup_authorsArray,
    //     minChars: 0,
    //     onSelect: function (suggestion) {
    //         /*$('#author_selection').html();*/
    //     },
    //     showNoSuggestionNotice: true,
    //     noSuggestionNotice: 'لا يوجد محاضرين تطابق كلمتك',
    // });
    
	// Initialize autocomplete with local lookup:
    // $('#kh_channel').devbridgeAutocomplete({
    //     lookup: popup_channelsArray,
    //     minChars: 0,
    //     onSelect: function (suggestion) {
    //         /*$('#author_selection').html();*/
    //     },
    //     showNoSuggestionNotice: true,
    //     noSuggestionNotice: 'لا يوجد قنوات تطابق كلمتك',
    // });
	/*=========== POPUP ==============*/
	//  $('#w2a_kh_author_name').devbridgeAutocomplete({
    //     lookup: popup_authorsArray,
    //     minChars: 0,
    //     onSelect: function (suggestion) {
    //         /*$('#author_selection').html();*/
    //     },
    //     showNoSuggestionNotice: true,
    //     noSuggestionNotice: 'لا يوجد محاضرين تطابق كلمتك',
    // });
    
	// Initialize autocomplete with local lookup:
    // $('#w2a_kh_channel').devbridgeAutocomplete({
    //     lookup: popup_channelsArray,
    //     minChars: 0,
    //     onSelect: function (suggestion) {
    //         /*$('#author_selection').html();*/
    //     },
    //     showNoSuggestionNotice: true,
    //     noSuggestionNotice: 'لا يوجد قنوات تطابق كلمتك',
    // });
	
    // Initialize autocomplete with custom appendTo:
   /* $('#autocomplete-custom-append').autocomplete({
        lookup: countriesArray,
        appendTo: '#suggestions-container'
    });

    // Initialize autocomplete with custom appendTo:
    $('#autocomplete-dynamic').autocomplete({
        lookup: countriesArray
    });*/
});
