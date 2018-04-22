# Upgrade guide

- [Upgrading to 1.1 from 1.0](#upgrade-1.1)

<a name="upgrade-1.1"></a>
## Upgrading To 1.1

In order to allow for embedding of forms in existing pages the default markup of the manager component slightly changed.

If you customized the markup (and only then), you have to adjust it after the upgrade by wrapping it with the following code:

    {% if not formstoreManager.authenticated %}

        ... your adjusted markup goes here ...

    {% else %}

        {{ formstoreManager.app | raw }}

    {% endif %}
 
In this way, the form manager ``formstoreManager.app`` will be embedded into the page if the component property ``embedded`` is enabled.

Refer to the documentation to learn more about the new features.
