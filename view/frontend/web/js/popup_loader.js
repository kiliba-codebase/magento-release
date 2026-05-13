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

        try {
            return {
                type: searchParams.get("kiliba_popup_preview_type"),
                data: JSON.parse(searchParams.get("kiliba_popup_preview_data")),
                lang: searchParams.get("kiliba_popup_preview_lang"),
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
