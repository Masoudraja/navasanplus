# Translation Guide for MNS Navasan Plus

## Overview
MNS Navasan Plus plugin is fully internationalized and ready for translation. This guide explains how to translate the plugin into your language.

## Files Structure
```
languages/
├── mns-navasan-plus.pot          # Template file (for translators)
├── mns-navasan-plus-en_US.po     # English translation
├── mns-navasan-plus-en_US.mo     # English compiled translation
├── mns-navasan-plus-fa_IR.po     # Persian/Farsi translation
└── mns-navasan-plus-fa_IR.mo     # Persian/Farsi compiled translation
```

## Creating a New Translation

### Method 1: Using Poedit (Recommended)
1. Download and install [Poedit](https://poedit.net/)
2. Open `mns-navasan-plus.pot` in Poedit
3. Create a new translation for your language
4. Save the `.po` file as `mns-navasan-plus-{locale}.po` (e.g., `mns-navasan-plus-de_DE.po` for German)
5. Poedit will automatically generate the `.mo` file

### Method 2: Manual Translation
1. Copy `mns-navasan-plus.pot` to `mns-navasan-plus-{locale}.po`
2. Edit the file header with proper language information
3. Translate each `msgstr ""` entry
4. Compile using `msgfmt`:
   ```bash
   msgfmt mns-navasan-plus-{locale}.po -o mns-navasan-plus-{locale}.mo
   ```

## Language Codes
Common language codes for file naming:
- English (US): `en_US`
- Spanish: `es_ES`
- French: `fr_FR` 
- German: `de_DE`
- Italian: `it_IT`
- Portuguese: `pt_PT`
- Russian: `ru_RU`
- Arabic: `ar`
- Persian/Farsi: `fa_IR`
- Turkish: `tr_TR`
- Dutch: `nl_NL`

## Installation
1. Place your translated `.po` and `.mo` files in the `languages/` directory
2. Set your WordPress site language in `Settings > General > Site Language`
3. The plugin will automatically load the appropriate translation

## Text Domain
All translatable strings use the text domain: `mns-navasan-plus`

## JavaScript Translations
The plugin supports JavaScript translations using WordPress's built-in `wp.i18n` functionality. JavaScript strings are automatically handled when you compile the `.po` files.

## RTL Support
The plugin includes built-in RTL (Right-to-Left) support for languages like Persian, Arabic, and Hebrew. The JavaScript functions include:
- Automatic Persian number conversion for RTL languages
- RTL-aware layout adjustments

## Contributing Translations
If you've created a translation, consider contributing it back to the community:
1. Test your translation thoroughly
2. Submit the `.po` and `.mo` files to the plugin developer
3. Include information about the language and locale

## Updating Translations
When the plugin is updated with new strings:
1. Download the latest `.pot` file
2. Update your `.po` file using Poedit's "Update from POT" feature
3. Translate any new strings
4. Recompile the `.mo` file

## Translation Status
### Currently Available:
- ✅ English (en_US) - 100% complete
- ✅ Persian/Farsi (fa_IR) - 100% complete

### Need Translations:
- Spanish (es_ES)
- French (fr_FR)
- German (de_DE)
- Arabic (ar)
- Other languages...

## Support
For translation support or questions:
- Check the plugin documentation
- Contact the plugin developer
- Use WordPress translation tools and community

## Technical Notes
- The plugin uses WordPress's standard internationalization functions: `__()`, `_e()`, `esc_html__()`, etc.
- Text domain is loaded using `load_plugin_textdomain()`
- JavaScript translations are handled via `wp_set_script_translations()`
- All user-facing strings are translatable
- Persian translations include cultural adaptations for currency and business terms

## File Format Example
```po
# Persian translation header
msgid ""
msgstr ""
"Project-Id-Version: MNS Navasan Plus 1.0.1\n"
"Language: fa_IR\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\n"

# Example translation
msgid "Navasan Plus"
msgstr "نوسان پلاس"
```

Happy translating! 🌍