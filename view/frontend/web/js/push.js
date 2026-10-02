/* global window, document, navigator, localStorage, sessionStorage, Notification */

(() => {
  const isIosDevice = /iPad|iPhone|iPod/.test(navigator.userAgent)
    || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
  const isStandalone = window.matchMedia?.("(display-mode: standalone)").matches || navigator.standalone === true;
  if(window.__kilibaPushRuntime || !("serviceWorker" in navigator)) return;
  if((!isIosDevice || isStandalone) && (!("PushManager" in window) || !("Notification" in window))) return;
  window.__kilibaPushRuntime = true;

  const endpoint = window.kpushendpoint;
  const publicConfigurationUrl = window.kpushconfigurl;
  const storageNamespace = String(window.kpushnamespace || publicConfigurationUrl).replace(/[^a-z0-9_-]/gi, "_");
  const storagePrefix = `kiliba_push_${storageNamespace}_`;
  const pageViewKey = `${storagePrefix}pageviews`;
  const dismissedKey = `${storagePrefix}dismissed_at`;
  const sessionDisplayKey = `${storagePrefix}session_displays`;
  const subscriptionIdKey = `${storagePrefix}subscription_id`;
  const heartbeatAtKey = `${storagePrefix}heartbeat_at`;
  const iosPageViewKey = `${storagePrefix}ios_pageviews`;
  const iosDismissedKey = `${storagePrefix}ios_dismissed_at`;
  const iosSessionDisplayKey = `${storagePrefix}ios_session_displays`;
  const launcherSessionDismissedKey = `${storagePrefix}launcher_session_dismissed`;
  const workerUrl = `${endpoint}${endpoint.includes("?") ? "&" : "?"}worker=1`;
  const heartbeatIntervalMs = 24 * 60 * 60 * 1000;
  const publicConfigurationCacheKey = `kiliba_push_config:${publicConfigurationUrl || "unavailable"}`;
  const publicConfigurationCacheDurationMs = 5 * 60 * 1000;
  const supportedLanguages = ["fr", "en", "es", "de", "it", "nl", "pt", "nb", "sv"];
  const defaultIosInstallationWordings = {
    fr: { title: "Activez les notifications sur iPhone", tutorialButton: "Voir comment faire" },
    en: { title: "Enable notifications on iPhone", tutorialButton: "See how" },
    es: { title: "Activa las notificaciones en el iPhone", tutorialButton: "Ver cómo hacerlo" },
    de: { title: "Benachrichtigungen auf dem iPhone aktivieren", tutorialButton: "Anleitung anzeigen" },
    it: { title: "Attiva le notifiche su iPhone", tutorialButton: "Scopri come" },
    nl: { title: "Schakel meldingen in op iPhone", tutorialButton: "Bekijk hoe" },
    pt: { title: "Ative as notificações no iPhone", tutorialButton: "Ver como fazer" },
    nb: { title: "Aktiver varslinger på iPhone", tutorialButton: "Se hvordan" },
    sv: { title: "Aktivera notiser på iPhone", tutorialButton: "Se hur du gör" },
  };
  const iosTutorialWordings = {
    fr: { heading: "Recevoir les notifications sur iPhone", share: "Touchez le bouton Partager de votre navigateur.", home: "Sélectionnez « Sur l’écran d’accueil ».", open: "Touchez « Ajouter », puis ouvrez la boutique depuis sa nouvelle icône.", requirement: "Nécessite iOS ou iPadOS 16.4 ou une version ultérieure.", close: "Fermer" },
    en: { heading: "Receive notifications on iPhone", share: "Tap the Share button in your browser.", home: "Select “Add to Home Screen”.", open: "Tap “Add”, then open the store from its new icon.", requirement: "Requires iOS or iPadOS 16.4 or later.", close: "Close" },
    es: { heading: "Recibe notificaciones en el iPhone", share: "Toca el botón Compartir de tu navegador.", home: "Selecciona «Añadir a pantalla de inicio».", open: "Toca «Añadir» y abre la tienda desde su nuevo icono.", requirement: "Requiere iOS o iPadOS 16.4 o una versión posterior.", close: "Cerrar" },
    de: { heading: "Benachrichtigungen auf dem iPhone erhalten", share: "Tippe in deinem Browser auf „Teilen“.", home: "Wähle „Zu Home-Bildschirm hinzufügen“.", open: "Tippe auf „Hinzufügen“ und öffne den Shop anschließend über das neue Symbol.", requirement: "Erfordert iOS oder iPadOS 16.4 oder neuer.", close: "Schließen" },
    it: { heading: "Ricevi le notifiche su iPhone", share: "Tocca il pulsante Condividi nel browser.", home: "Seleziona “Aggiungi alla schermata Home”.", open: "Tocca “Aggiungi”, quindi apri il negozio dalla nuova icona.", requirement: "Richiede iOS o iPadOS 16.4 o versioni successive.", close: "Chiudi" },
    nl: { heading: "Ontvang meldingen op iPhone", share: "Tik in je browser op de knop Delen.", home: "Kies ‘Zet op beginscherm’.", open: "Tik op ‘Voeg toe’ en open de winkel via het nieuwe pictogram.", requirement: "Vereist iOS of iPadOS 16.4 of nieuwer.", close: "Sluiten" },
    pt: { heading: "Receba notificações no iPhone", share: "Toque no botão Partilhar do navegador.", home: "Selecione «Adicionar ao ecrã principal».", open: "Toque em «Adicionar» e abra a loja através do novo ícone.", requirement: "Requer iOS ou iPadOS 16.4 ou posterior.", close: "Fechar" },
    nb: { heading: "Motta varslinger på iPhone", share: "Trykk på Del-knappen i nettleseren.", home: "Velg «Legg til på Hjem-skjerm».", open: "Trykk på «Legg til», og åpne deretter butikken fra det nye ikonet.", requirement: "Krever iOS eller iPadOS 16.4 eller nyere.", close: "Lukk" },
    sv: { heading: "Få notiser på iPhone", share: "Tryck på Dela-knappen i webbläsaren.", home: "Välj ”Lägg till på hemskärmen”.", open: "Tryck på ”Lägg till” och öppna sedan butiken via den nya ikonen.", requirement: "Kräver iOS eller iPadOS 16.4 eller senare.", close: "Stäng" },
  };
  const toUint8Array = value => {
    const padding = "=".repeat((4 - value.length % 4) % 4);
    const raw = atob((value + padding).replace(/-/g, "+").replace(/_/g, "/"));
    return Uint8Array.from([...raw].map(character => character.charCodeAt(0)));
  };
  const getSubscriptionId = async subscription => {
    const endpoint = subscription?.endpoint || subscription?.toJSON?.().endpoint;
    if(!endpoint || !window.crypto?.subtle) return null;
    try {
      const digest = await window.crypto.subtle.digest("SHA-256", new TextEncoder().encode(endpoint));
      return [...new Uint8Array(digest)].map(value => value.toString(16).padStart(2, "0")).join("");
    } catch (_) {
      return null;
    }
  };
  const clearSubscriptionState = () => {
    localStorage.removeItem(subscriptionIdKey);
    localStorage.removeItem(heartbeatAtKey);
  };
  const request = async (body, method = "POST") => {
    const response = await fetch(endpoint, { method, headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });
    if(!response.ok) throw new Error("Kiliba Push request failed");
    return await response.json();
  };
  const waitForActiveServiceWorker = registration => {
    if(registration.active?.state === "activated") return Promise.resolve(registration);
    const worker = registration.installing || registration.waiting || registration.active;
    if(!worker) return Promise.reject(new Error("Kiliba Push service worker is unavailable"));
    return new Promise((resolve, reject) => {
      let interval = 0;
      const timeout = window.setTimeout(() => {
        window.clearInterval(interval);
        worker.removeEventListener("statechange", handleStateChange);
        reject(new Error("Kiliba Push service worker activation timed out"));
      }, 10_000);
      const finish = callback => {
        window.clearTimeout(timeout);
        window.clearInterval(interval);
        worker.removeEventListener("statechange", handleStateChange);
        callback(registration);
      };
      const handleStateChange = () => {
        if(registration.active?.state === "activated") finish(resolve);
        else if(worker.state === "redundant") finish(() => reject(new Error("Kiliba Push service worker activation failed")));
      };
      worker.addEventListener("statechange", handleStateChange);
      interval = window.setInterval(handleStateChange, 25);
      handleStateChange();
    });
  };
  const getPublicConfiguration = async () => {
    try {
      const cached = JSON.parse(localStorage.getItem(publicConfigurationCacheKey) || "null");
      if(cached?.expiresAt > Date.now() && Object.prototype.hasOwnProperty.call(cached, "value")) return cached.value;
    } catch (_) {}
    const response = await fetch(publicConfigurationUrl, { credentials: "omit" });
    const remainingCacheDurationMs = Math.max(
      0,
      publicConfigurationCacheDurationMs - (Number(response.headers.get("Age") || 0) * 1000)
    );
    let value = null;
    if(response.ok) {
      try { value = await response.json(); }
      catch (_) { value = null; }
    }
    try {
      if(remainingCacheDurationMs > 0) {
        localStorage.setItem(publicConfigurationCacheKey, JSON.stringify({
          expiresAt: Date.now() + remainingCacheDurationMs,
          value,
        }));
      }
    } catch (_) {}
    return value;
  };
  const metadata = () => ({
    locale: window.kpushlocale || document.documentElement.lang || navigator.language || "fr",
    browser: /Edg/.test(navigator.userAgent) ? "Edge" : /Firefox|FxiOS/.test(navigator.userAgent) ? "Firefox" : /Chrome|CriOS/.test(navigator.userAgent) ? "Chrome" : /Safari/.test(navigator.userAgent) ? "Safari" : "Chrome",
    device: /iPad/.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1) ? "iPad" : /iPhone|iPod/.test(navigator.userAgent) ? "iPhone" : /Android/.test(navigator.userAgent) ? "Android" : "Desktop",
  });
  const wordingFor = config => {
    const localized = config.wordings[String(window.kpushlocale || document.documentElement.lang || "").slice(0, 2)];
    return localized && Object.values(localized).every(value => String(value || "").trim())
      ? localized
      : config.wordings[config.fallbackLanguage];
  };
  const languageFor = config => {
    const candidate = String(window.kpushlocale || document.documentElement.lang || navigator.language || "").slice(0, 2).toLowerCase();
    return supportedLanguages.includes(candidate) ? candidate : config.fallbackLanguage;
  };
  const iosInstallationWordingFor = config => {
    const language = languageFor(config);
    const localized = config.iosInstallation?.wordings?.[language];
    const fallback = config.iosInstallation?.wordings?.[config.fallbackLanguage];
    const isComplete = wording => ["title", "tutorialButton"]
      .every(field => String(wording?.[field] || "").trim());
    const configured = isComplete(localized) ? localized : isComplete(fallback) ? fallback : null;
    return {
      ...defaultIosInstallationWordings[language],
      ...(configured || {}),
    };
  };
  const iosTutorialWordingFor = config => iosTutorialWordings[languageFor(config)]
    || iosTutorialWordings[config.fallbackLanguage]
    || iosTutorialWordings.fr;
  const stylePromptContainer = (container, config) => {
    Object.assign(container.style, { position: "fixed", zIndex: "2147483000", width: "min(340px, calc(100vw - 32px))", padding: "18px", borderRadius: "12px", boxShadow: "0 12px 30px rgba(0,0,0,.18)", display: "flex", flexDirection: "column", gap: "12px", background: config.design.backgroundColor, color: config.design.textColor });
    container.style[config.design.position.includes("bottom") ? "bottom" : "top"] = "20px";
    container.style[config.design.position.includes("left") ? "left" : "right"] = "20px";
  };
  const stylePromptButton = (button, config, secondary = false) => {
    Object.assign(button.style, { padding: "10px 14px", borderRadius: "8px", cursor: "pointer", font: "inherit", fontWeight: "600", lineHeight: "1.2" });
    Object.assign(button.style, secondary
      ? { border: `1px solid ${config.design.textColor}`, background: "transparent", color: config.design.textColor }
      : { border: `1px solid ${config.design.buttonColor}`, background: config.design.buttonColor, color: config.design.buttonTextColor });
  };
  const showLauncher = (config, openPrompt) => {
    if(!config.display.showLauncher
      || sessionStorage.getItem(launcherSessionDismissedKey) === "1"
      || document.querySelector("[data-kiliba-push-launcher=\"reminder\"]")
      || document.querySelector("[data-kiliba-push=\"prompt\"], [data-kiliba-push=\"ios-prompt\"]")) return;
    const wrapper = document.createElement("div");
    wrapper.setAttribute("data-kiliba-push-launcher", "reminder");
    const launcherPosition = ["bottom-left", "bottom-right", "top-left", "top-right"].includes(config.design.launcherPosition)
      ? config.design.launcherPosition
      : "bottom-right";
    Object.assign(wrapper.style, { position: "fixed", zIndex: "2147482999", width: "44px", height: "44px" });
    wrapper.style[launcherPosition.includes("bottom") ? "bottom" : "top"] = "16px";
    wrapper.style[launcherPosition.includes("left") ? "left" : "right"] = "16px";
    const launcher = document.createElement("button");
    launcher.type = "button";
    launcher.textContent = "🔔";
    launcher.setAttribute("aria-label", wordingFor(config).title);
    Object.assign(launcher.style, { width: "44px", height: "44px", padding: "0", borderRadius: "50%", border: "0", cursor: "pointer", background: config.design.launcherBackgroundColor, boxShadow: "0 12px 28px rgba(0,0,0,.2)", fontSize: "18px" });
    const close = document.createElement("button");
    close.type = "button";
    close.textContent = "×";
    close.setAttribute("aria-label", iosTutorialWordingFor(config).close);
    Object.assign(close.style, { position: "absolute", top: "-7px", right: "-7px", display: "grid", placeItems: "center", width: "20px", height: "20px", padding: "0", border: "2px solid #fff", borderRadius: "50%", cursor: "pointer", background: "#232323", color: "#fff", font: "bold 14px/1 sans-serif" });
    launcher.onclick = () => {
      wrapper.remove();
      openPrompt();
    };
    close.onclick = () => {
      sessionStorage.setItem(launcherSessionDismissedKey, "1");
      wrapper.remove();
    };
    wrapper.append(launcher, close);
    document.body.appendChild(wrapper);
  };
  const ensureIosManifest = config => {
    if(document.querySelector("link[data-kiliba-push-manifest]")) return;
    const language = languageFor(config);
    const icon = String(config.design.notificationIconUrl || "").trim();
    const manifest = document.createElement("link");
    manifest.rel = "manifest";
    manifest.href = `${endpoint}${endpoint.includes("?") ? "&" : "?"}manifest=1&lang=${encodeURIComponent(language)}${icon ? `&icon=${encodeURIComponent(icon)}` : ""}`;
    manifest.setAttribute("data-kiliba-push-manifest", "1");
    document.head.appendChild(manifest);
    if(icon && !document.querySelector("link[data-kiliba-push-touch-icon]")) {
      const touchIcon = document.createElement("link");
      touchIcon.rel = "apple-touch-icon";
      touchIcon.href = icon;
      touchIcon.setAttribute("data-kiliba-push-touch-icon", "1");
      document.head.appendChild(touchIcon);
    }
  };
  const showIosTutorial = (container, config) => {
    const tutorial = iosTutorialWordingFor(config);
    container.innerHTML = "<strong></strong><ol><li></li><li></li><li></li></ol><small></small><div><button data-close></button></div>";
    container.querySelector("strong").textContent = tutorial.heading;
    const steps = container.querySelectorAll("li");
    [tutorial.share, tutorial.home, tutorial.open].forEach((value, index) => { steps[index].textContent = value; });
    Object.assign(container.querySelector("ol").style, { margin: "0", paddingLeft: "22px", display: "flex", flexDirection: "column", gap: "8px", lineHeight: "1.4" });
    const requirement = container.querySelector("small");
    requirement.textContent = tutorial.requirement;
    Object.assign(requirement.style, { opacity: ".72", lineHeight: "1.4" });
    const close = container.querySelector("[data-close]");
    close.textContent = tutorial.close;
    stylePromptButton(close, config);
    close.onclick = () => {
      localStorage.setItem(iosDismissedKey, String(Date.now()));
      container.remove();
      showLauncher(config, () => buildIosInstallPrompt(config));
    };
  };
  const buildIosInstallPrompt = config => {
    const wording = iosInstallationWordingFor(config);
    const container = document.createElement("div");
    container.setAttribute("data-kiliba-push", "ios-prompt");
    stylePromptContainer(container, config);
    container.innerHTML = "<strong></strong><div><button data-tutorial></button><button data-later></button></div>";
    container.querySelector("strong").textContent = wording.title;
    const tutorialButton = container.querySelector("[data-tutorial]");
    const laterButton = container.querySelector("[data-later]");
    tutorialButton.textContent = wording.tutorialButton;
    laterButton.textContent = wordingFor(config).laterButton;
    Object.assign(tutorialButton.parentElement.style, { display: "flex", alignItems: "center", flexWrap: "wrap", gap: "8px" });
    stylePromptButton(tutorialButton, config);
    stylePromptButton(laterButton, config, true);
    tutorialButton.onclick = () => {
      ensureIosManifest(config);
      showIosTutorial(container, config);
    };
    laterButton.onclick = () => {
      localStorage.setItem(iosDismissedKey, String(Date.now()));
      container.remove();
      showLauncher(config, () => buildIosInstallPrompt(config));
    };
    document.body.appendChild(container);
  };
  const isPublicConfigurationUsable = config => {
    const fallbackWording = config?.wordings?.[config?.fallbackLanguage];
    return Boolean(
      config?.version === 4
      && config?.enabled
      && config.publicKey
      && config.design
      && config.display
      && typeof config.display.showLauncher === "boolean"
      && fallbackWording
      && Object.values(fallbackWording).every(value => String(value || "").trim())
    );
  };

  const showEmailStep = (container, config, subscriptionId) => {
    const wording = wordingFor(config);
    container.innerHTML = "<strong></strong><span></span><label></label><input type=\"email\"><div><button data-submit></button><button data-skip></button></div>";
    const title = container.querySelector("strong");
    const body = container.querySelector("span");
    const label = container.querySelector("label");
    title.textContent = wording.emailTitle;
    body.textContent = wording.emailBody;
    label.textContent = wording.emailLabel;
    Object.assign(title.style, { fontSize: "16px", lineHeight: "1.3" });
    Object.assign(body.style, { lineHeight: "1.45" });
    Object.assign(label.style, { fontSize: "13px", fontWeight: "600", marginTop: "2px" });
    const input = container.querySelector("input");
    input.placeholder = wording.emailPlaceholder;
    Object.assign(input.style, { width: "100%", boxSizing: "border-box", padding: "10px 12px", border: `1px solid ${config.design.textColor}`, borderRadius: "8px", background: config.design.backgroundColor, color: config.design.textColor, font: "inherit" });
    const actions = input.nextElementSibling;
    Object.assign(actions.style, { display: "flex", alignItems: "center", flexWrap: "wrap", gap: "8px" });
    const submit = container.querySelector("[data-submit]");
    const skip = container.querySelector("[data-skip]");
    submit.textContent = wording.emailSubmitButton;
    skip.textContent = wording.emailSkipButton;
    for(const button of [submit, skip]) {
      Object.assign(button.style, { padding: "10px 14px", borderRadius: "8px", cursor: "pointer", font: "inherit", fontWeight: "600", lineHeight: "1.2" });
    }
    Object.assign(submit.style, { border: `1px solid ${config.design.buttonColor}`, background: config.design.buttonColor, color: config.design.buttonTextColor });
    Object.assign(skip.style, { border: `1px solid ${config.design.textColor}`, background: "transparent", color: config.design.textColor });
    skip.onclick = () => container.remove();
    submit.onclick = async () => {
      if(!input.checkValidity()) return input.reportValidity();
      await request({ action: "identity", subscriptionId, email: input.value });
      container.remove();
    };
  };

  const buildPrompt = config => {
    // A granted permission is not an invitation to ask again, even if the
    // browser subscription was removed or could not be created.
    if(Notification.permission !== "default") return;
    const wording = wordingFor(config);
    const container = document.createElement("div");
    container.setAttribute("data-kiliba-push", "prompt");
    stylePromptContainer(container, config);
    container.innerHTML = "<strong></strong><span></span><div><button data-allow></button><button data-later></button></div>";
    container.querySelector("strong").textContent = wording.title;
    container.querySelector("span").textContent = wording.body;
    const allow = container.querySelector("[data-allow]");
    const later = container.querySelector("[data-later]");
    Object.assign(allow.parentElement.style, { display: "flex", alignItems: "center", flexWrap: "wrap", gap: "8px" });
    stylePromptButton(allow, config);
    stylePromptButton(later, config, true);
    allow.textContent = wording.allowButton;
    later.textContent = wording.laterButton;
    later.onclick = () => {
      localStorage.setItem(dismissedKey, String(Date.now()));
      container.remove();
      showLauncher(config, () => buildPrompt(config));
    };
    allow.onclick = async () => {
      allow.disabled = true;
      try {
        localStorage.setItem(dismissedKey, String(Date.now()));
        if(await Notification.requestPermission() !== "granted") return container.remove();
        const registration = await waitForActiveServiceWorker(
          await navigator.serviceWorker.register(workerUrl, { scope: window.kpushscope })
        );
        const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toUint8Array(config.publicKey) });
        const result = await request({
          action: "subscribe",
          subscription: subscription.toJSON(),
          previousSubscriptionId: localStorage.getItem(subscriptionIdKey),
          ...metadata(),
        });
        localStorage.setItem(subscriptionIdKey, result.subscriptionId);
        localStorage.setItem(heartbeatAtKey, String(Date.now()));
        if(result.email || config.display.collectEmailAfterPermission === false) container.remove();
        else showEmailStep(container, config, result.subscriptionId);
      } finally { allow.disabled = false; }
    };
    document.body.appendChild(container);
  };

  const run = async () => {
    if(!publicConfigurationUrl) return;
    const knownSubscriptionId = localStorage.getItem(subscriptionIdKey);
    const config = await getPublicConfiguration();
    if(!config) return;
    if(!isPublicConfigurationUsable(config)) return;
    if(isIosDevice && !isStandalone) {
      const dismissedAt = Number(localStorage.getItem(iosDismissedKey) || 0);
      if(Date.now() - dismissedAt < config.display.redisplayAfterDays * 86400000) {
        return;
      }
      const displayed = Number(sessionStorage.getItem(iosSessionDisplayKey) || 0);
      if(displayed >= config.display.maxDisplaysPerSession) {
        return;
      }
      const pageViews = Number(localStorage.getItem(iosPageViewKey) || 0) + 1;
      localStorage.setItem(iosPageViewKey, String(pageViews));
      const show = () => {
        if(document.querySelector("[data-kiliba-push=\"prompt\"], [data-kiliba-push=\"ios-prompt\"]")) return;
        buildIosInstallPrompt(config);
        sessionStorage.setItem(iosSessionDisplayKey, String(displayed + 1));
      };
      if(config.display.triggerMode === "pageviews") {
        if(pageViews >= config.display.pageViews) show();
      } else if(config.display.triggerMode === "delay") {
        setTimeout(show, config.display.delaySeconds * 1000);
      } else if(pageViews >= config.display.pageViews) show();
      else setTimeout(show, config.display.delaySeconds * 1000);
      return;
    }
    if(!("PushManager" in window) || !("Notification" in window)) return;
    if(Notification.permission === "denied") {
      if(knownSubscriptionId) {
        await request({ action: "revoked", subscriptionId: knownSubscriptionId });
        clearSubscriptionState();
      }
      return;
    }
    const registration = await navigator.serviceWorker.getRegistration(window.kpushscope);
    const existing = await registration?.pushManager.getSubscription();
    if(existing) {
      const currentSubscriptionId = await getSubscriptionId(existing);
      const endpointRotated = Boolean(currentSubscriptionId && currentSubscriptionId !== knownSubscriptionId);
      let subscriptionId = knownSubscriptionId;
      if(!knownSubscriptionId || endpointRotated) {
        const result = await request({
          action: "subscribe",
          subscription: existing.toJSON(),
          previousSubscriptionId: knownSubscriptionId,
          ...metadata(),
        });
        subscriptionId = result.subscriptionId;
        localStorage.setItem(subscriptionIdKey, subscriptionId);
        localStorage.setItem(heartbeatAtKey, String(Date.now()));
      } else {
        const now = Date.now();
        const lastHeartbeatAt = Number(localStorage.getItem(heartbeatAtKey) || 0);
        const heartbeatIsRecent = lastHeartbeatAt > 0
          && lastHeartbeatAt <= now
          && now - lastHeartbeatAt < heartbeatIntervalMs;
        if(!heartbeatIsRecent) {
          await request({ action: "heartbeat", subscriptionId });
          localStorage.setItem(heartbeatAtKey, String(Date.now()));
        }
      }
      return;
    }
    if(knownSubscriptionId) {
      await request({ action: "revoked", subscriptionId: knownSubscriptionId });
      clearSubscriptionState();
    }
    if(Notification.permission === "granted") return;
    const dismissedAt = Number(localStorage.getItem(dismissedKey) || 0);
    if(Date.now() - dismissedAt < config.display.redisplayAfterDays * 86400000) {
      return;
    }
    const displayed = Number(sessionStorage.getItem(sessionDisplayKey) || 0);
    if(displayed >= config.display.maxDisplaysPerSession) {
      return;
    }
    const pageViews = Number(localStorage.getItem(pageViewKey) || 0) + 1;
    localStorage.setItem(pageViewKey, String(pageViews));
    const show = () => {
      // Recheck at display time: another tab or a permission change may have
      // made a scheduled invitation ineligible. Installed iOS uses this too.
      if(Notification.permission !== "default"
        || Date.now() - Number(localStorage.getItem(dismissedKey) || 0) < config.display.redisplayAfterDays * 86400000
        || Number(sessionStorage.getItem(sessionDisplayKey) || 0) >= config.display.maxDisplaysPerSession
        || document.querySelector('[data-kiliba-push="prompt"]')) return;
      buildPrompt(config);
      sessionStorage.setItem(sessionDisplayKey, String(Number(sessionStorage.getItem(sessionDisplayKey) || 0) + 1));
    };
    if(config.display.triggerMode === "pageviews") {
      if(pageViews >= config.display.pageViews) show();
    } else if(config.display.triggerMode === "delay") {
      setTimeout(show, config.display.delaySeconds * 1000);
    } else if(pageViews >= config.display.pageViews) show();
    else setTimeout(show, config.display.delaySeconds * 1000);
  };
  const start = () => run().catch(() => {});
  if(document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start, { once: true });
  } else {
    start();
  }
})();
