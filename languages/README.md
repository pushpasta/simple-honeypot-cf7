# Translation Guide

Thank you for helping improve the plugin's translations.

Translations are managed using standard WordPress gettext files and are always welcome.

## Getting Started

The translation template is located in:

```text
languages/simple-honeypot-cf7.pot
```

Open the POT file with a translation editor such as Poedit and create a new translation for your language.

Save the translated files using the appropriate WordPress locale:

```text
simple-honeypot-cf7-xx_XX.po
simple-honeypot-cf7-xx_XX.mo
```

When submitting a translation, only the `.po` file is required unless explicitly requested otherwise.

## Translation Guidelines

- Use clear and natural language.
- Keep terminology consistent throughout the plugin.
- Translate the meaning, not necessarily the literal wording.
- Preserve placeholders such as `%s`, `%d`, `%1$s`, and `%2$d`.
- Do not translate HTML tags or code snippets.
- Test translations whenever possible.

## Submitting Translations

You can contribute translations by:

1. Opening a pull request containing the translated `.po` file.
2. Opening an issue and attaching the translation files if you are unable to create a pull request.

## For Developers

### Updating the Translation Template

Developers can regenerate the translation template with:

```
composer make-pot
```

## Translation Contributors

Thank you to everyone who has contributed translations.

| Locale Code | Language (English Name) | Translator(s) |
| ----------- | ----------------------- | ------------- |
| xx_XX       | Language                | @username     |

To be listed above, please include your preferred name or GitHub username in your pull request or issue.

---

> [!NOTE]
> Working with `.po` files and translation tools may be less convenient than translating directly in a browser. A hosted translation platform may be introduced in the future to make contributing translations easier.
