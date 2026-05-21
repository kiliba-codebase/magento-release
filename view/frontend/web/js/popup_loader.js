(function () {
    const popupConfigurationElement = document.getElementById("kilibapopup");
    const navigatorLanguage = window.navigator && window.navigator.language ? window.navigator.language : null;
    const defaultLocale = navigatorLanguage ? navigatorLanguage.split("-")[0] : "fr";
    const popupDataset = popupConfigurationElement && popupConfigurationElement.dataset ? popupConfigurationElement.dataset : {};
    const popupType = popupDataset.popupType || "promo-code-first-purchase";
    const popupTypeKey = popupDataset.popupTypeKey || "promoCodeFirstPurchase";
    const popupCookieName = `kpopup_${popupTypeKey.toLowerCase()}`;
    const popupCssUrl = popupDataset.popupCssUrl;
    const popupJsUrl = popupDataset.popupJsUrl;

    function getPreviewConfig() {
        const searchParams = new URLSearchParams(window.location.search);
        if (!searchParams.has("kiliba_popup_preview_type")) {
            return null;
        }

        const type = searchParams.get("kiliba_popup_preview_type");
        const lang = searchParams.get("kiliba_popup_preview_lang");
        const previewToken = searchParams.get("kiliba_popup_preview_token");

        if (previewToken) {
            return {
                type,
                token: previewToken,
                lang,
                debug: true
            };
        }

        try {
            return {
                type,
                data: JSON.parse(searchParams.get("kiliba_popup_preview_data")),
                lang,
                debug: true
            };
        } catch (error) {
            console.warn("Unable to parse Kiliba popup preview configuration", error);
            return null;
        }
    }

    function getPopupLocale() {
        const locale = popupDataset.locale;
        return locale ? locale.split("_")[0] : defaultLocale;
    }

    function isPopupDefinitelyClosed() {
        return document.cookie.includes(`${popupCookieName}=close`);
    }

    function injectPopupAssets(config) {
        if (!popupCssUrl || !popupJsUrl) {
            return;
        }

        if (window.kilibaPopupBootstrapConfig || document.getElementById("kiliba-popup-loader-script")) {
            return;
        }

        window.kilibaPopupBootstrapConfig = config;

        if (!document.getElementById("kiliba-popup-loader-style")) {
            const stylesheet = document.createElement("link");
            stylesheet.id = "kiliba-popup-loader-style";
            stylesheet.rel = "stylesheet";
            stylesheet.href = popupCssUrl;
            document.head.appendChild(stylesheet);
        }

        const script = document.createElement("script");
        script.id = "kiliba-popup-loader-script";
        script.src = popupJsUrl;
        document.body.appendChild(script);
    }

    async function loadPopupAssetsIfNeeded() {
        const previewConfig = getPreviewConfig();
        if (previewConfig && previewConfig.type === popupType) {
            if (previewConfig.token) {
                try {
                    const previewUrl = new URL(window.location.origin);
                    previewUrl.pathname = `/rest/V1/kiliba-connector/popup/${popupTypeKey}/preview`;
                    previewUrl.searchParams.set("token", previewConfig.token);

                    const response = await fetch(previewUrl, { method: "GET" });
                    if (!response.ok) {
                        return;
                    }

                    const apiResult = await response.json();
                    if (!apiResult || !apiResult[0] || !apiResult[0].success || !apiResult[0].payload || !apiResult[0].payload.data) {
                        return;
                    }

                    injectPopupAssets({
                        type: apiResult[0].payload.type || popupType,
                        data: apiResult[0].payload.data,
                        lang: apiResult[0].payload.lang || previewConfig.lang,
                        debug: true
                    });
                    return;
                } catch (error) {
                    console.error("Unable to resolve Kiliba popup preview", error);
                    return;
                }
            }

            injectPopupAssets(previewConfig);
            return;
        }

        if (isPopupDefinitelyClosed()) {
            return;
        }

        try {
            const popupConfigUrl = new URL(window.location.origin);
            popupConfigUrl.pathname = `/rest/V1/kiliba-connector/popup/${popupTypeKey}`;

            const response = await fetch(popupConfigUrl, { method: "GET" });
            if (!response.ok) {
                return;
            }

            const apiResult = await response.json();
            if (!apiResult || !apiResult[0] || !apiResult[0].success || !apiResult[0].configuration) {
                return;
            }

            injectPopupAssets({
                type: popupType,
                data: apiResult[0].configuration,
                lang: getPopupLocale(),
                debug: false
            });
        } catch (error) {
            console.error("Unable to bootstrap Kiliba popup", error);
        }
    }

    loadPopupAssetsIfNeeded();
})();
