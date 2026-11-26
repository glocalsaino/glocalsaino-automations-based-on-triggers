# GlocalSaino Automations Based on Triggers

Automatizaciones sin código disparador → acciones. Publicado en WordPress.org (1.3.5; 1.4.0 preparada con el submenú Extensions). **Repo público.**

- Prefijos internos (se mantienen pese al cambio de nombre): clases `GSAAT_`, globales `glocalsaino_aat_` / `glocalsaino-aat`, REST `glocalsaino-aat/v1`, JS `glocalsainoAatEditor` / `window.glocalsainoAat`.
- Tipos de disparador/acción en `GSAAT_Registry` (filtros `glocalsaino_aat_trigger_types` / `glocalsaino_aat_action_types`); `GSAAT_Engine::fire()`. Contrato para add-ons en `EXTENDING.md`: mantenerlo al día y subir `GSAAT_API_VERSION` si hay cambios incompatibles.
- "Wait" es una acción: lo que va detrás se encola en la tabla `jobs` y lo ejecuta WP-Cron.
- **Reglas de la revisión de WordPress.org:** ningún punto de entrada público lee `$_GET`/`$_POST` al cargar la página: enlace, botón, subida, CSV y widget son rutas REST bajo `glocalsaino-aat/v1`. Los "websites permitidos" del widget solo controlan dónde se muestra (frame-ancestors): **no** presentarlo nunca como protección frente a envíos directos a la API. Límite horario de ejecuciones públicas (`public_hourly_limit`).
- Captcha: solo hCaptcha (Plugin Check bloquea cloudflare.com).
- Webhooks y widget forman parte del plugin gratuito por decisión del producto.
- Add-on de pago: `glocalsaino-automations-bridge` (repo privado). Submenú Extensions (`GSAAT_Admin_Extensions`, último, `PHP_INT_MAX`).
- Workaround CF7: añade `_wpnonce` (wp_rest) vía `wpcf7_form_hidden_fields` porque CF7 elimina X-WP-Nonce.
- Subir versión por cada lote grande de funciones. Zip: `git -C ~/proyectos/wordpress/glocalsaino-automations-based-on-triggers archive --format=zip --prefix=glocalsaino-automations-based-on-triggers/ -o ~/Desktop/glocalsaino-automations-based-on-triggers-<versión>.zip HEAD` (ver ~/.claude/CLAUDE.md).
