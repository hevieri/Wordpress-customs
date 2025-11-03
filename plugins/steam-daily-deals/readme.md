# 🔥 Steam Daily Deals — WordPress Plugin


---


Lightweight WordPress plugin to display Steam daily deals via shortcode, Gutenberg block and REST endpoint. Pensado para integrarse rápido en temas y permitir override de plantillas.

---

## ✨ Características principales

- Mostrar listas de ofertas diarias y destacadas (shortcode + bloque).  
- Caché configurable para reducir llamadas externas.  
- Filtros por país/moneda, rango mínimo de descuento y cantidad máxima.  
- Plantillas sobrescribibles desde el theme (template override).  
- Endpoint REST público para consumo desde JS o integraciones.  
- Listo para traducción (i18n).

---

## ⚙️ Requisitos

- WordPress 5.8+  
- PHP 7.4+ (recomendado 8.0+)  
- cURL o allow_url_fopen habilitado

---

## 🚀 Instalación

1. Subir carpeta `steam-daily-deals` a `wp-content/plugins/`.  
2. Activar el plugin desde Plugins → Activar.  
3. Ir a Ajustes → Steam Daily Deals y configurar país, moneda y cache.

Instalación por línea de comando (desarrollo):
```bash
# desde la raíz de tu instalación WordPress
cp -r path/to/steam-daily-deals wp-content/plugins/
wp plugin activate steam-daily-deals
