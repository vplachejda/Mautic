//PageBundle
Mautic.pageOnLoad = function (container) {
    if (mQuery(container + ' #list-search').length) {
        Mautic.activateSearchAutocomplete('list-search', 'page.page');
    }

    if (mQuery(container + ' #page_template').length) {
        Mautic.toggleBuilderButton(mQuery('#page_template').val() == '');
    }

    //Handle autohide of "Redirect URL" field if "Redirect Type" is none
    if (mQuery(container + ' select[name="page[redirectType]"]').length) {
        //Auto-hide on page loading
        Mautic.autoHideRedirectUrl(container);

        //Auto-hide on select changing
        mQuery(container + ' select[name="page[redirectType]"]').chosen().change(function(){
            Mautic.autoHideRedirectUrl(container);
        });
    }

    var textarea = mQuery('#page_customHtml');

    mQuery(document).on('shown.bs.tab', function (e) {
        textarea.froalaEditor('popups.hideAll');
    });

    mQuery('a[href="#source-container"]').on('shown.bs.tab', function (e) {
        textarea.froalaEditor('html.set', textarea.val());
    });

    mQuery('.btn-builder').on('click', function (e) {
        textarea.froalaEditor('popups.hideAll');
    });

    Mautic.intiSelectTheme(mQuery('#page_template'));
};

Mautic.pageOnUnload = function (id) {
    mQuery('#page_customHtml').froalaEditor('popups.hideAll');
}

Mautic.getPageAbTestWinnerForm = function(abKey) {
    if (abKey && mQuery(abKey).val() && mQuery(abKey).closest('.form-group').hasClass('has-error')) {
        mQuery(abKey).closest('.form-group').removeClass('has-error');
        if (mQuery(abKey).next().hasClass('help-block')) {
            mQuery(abKey).next().remove();
        }
    }

    Mautic.activateLabelLoadingIndicator('page_variantSettings_winnerCriteria');

    var pageId = mQuery('#page_sessionId').val();
    var query  = "action=page:getAbTestForm&abKey=" + mQuery(abKey).val() + "&pageId=" + pageId;

    mQuery.ajax({
        url: mauticAjaxUrl,
        type: "POST",
        data: query,
        dataType: "json",
        success: function (response) {
            if (typeof response.html != 'undefined') {
                if (mQuery('#page_variantSettings_properties').length) {
                    mQuery('#page_variantSettings_properties').replaceWith(response.html);
                } else {
                    mQuery('#page_variantSettings').append(response.html);
                }

                if (response.html != '') {
                    Mautic.onPageLoad('#page_variantSettings_properties', response);
                }
            }

            Mautic.removeLabelLoadingIndicator();

        },
        error: function (request, textStatus, errorThrown) {
            Mautic.processAjaxError(request, textStatus, errorThrown);
            spinner.remove();
        },
        complete: function () {
            Mautic.removeLabelLoadingIndicator();
        }
    });
};

Mautic.autoHideRedirectUrl = function(container) {
    var select = mQuery(container + ' select[name="page[redirectType]"]');
    var input = mQuery(container + ' input[name="page[redirectUrl]"]');

    //If value is none we autohide the "Redirect URL" field and empty it
    if (select.val() == '') {
        input.closest('.form-group').hide();
        input.val('');
    } else {
        input.closest('.form-group').show();
    }
};