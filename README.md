# FormStore plugin for OctoberCMS

This plugin allows end users to create, edit and submit forms in an easy-to-use front-end interface. It can therefore be used to implement an online application system, self-hosted surveys, or any other backend-like form in the frontend.

**Features**

- Easy to use form manager
- Renders OctoberCMS YAML forms in the frontend with support for relations and file uploads
- Integrated passwordless email authentication
- Other custom authentication methods possible, e.g. via Rainlab.User plugin
- Option to restrict access to registered users only

### User experience

To start a form submission, the user has to provide her email address and receives a link to sign into the form manager. The user can log out at any time and request another login link later.

The form manager displays available forms and instructions and allows the user to create and manage submissions. The user can fill the forms and save their work at any time. It is also possible to cancel and remove submissions. Once everything is filled and validated, the user can submit the form. At this point, modifications of the submitted content are not longer possible, but the user can still view the submitted data.

The manager supports opening and closing dates for forms. If activated, the user is only allowed to submit forms between the specified open to close time range. Furthermore, a countdown component can be used to display a countdown to the opening of a form.

### Additional information

In future, the plugin might be extended to allow for

- Developer API to render custom frontend forms
- link based access (like in Google Forms)
- advanced submitter management (e.g. sending emails to all submitters)
- general user experience improvements
- ...

If you find this plugin useful, please consider donating to support its further development.

---

The plugin currently includes two components: Manager and Countdown.

### The Manager component

The manager component provides the main functionality and can be included in any CMS page that should serve as form manager. It is recommended to create a new CMS page since the page will be replaced with the form manager frontend once the user is logged in.

The Manager component has the following properties:

* `forms` - [set] specifies the forms that users can manage
* `embedded` - [checkbox] if activated the manager will be embedded into the page rather than replacing it
* `open_for_registration` [checkbox] If disabled, only existing submitters can access the form
* `login_mail_template` - [string] the email template of the login mail. Defaults to `nocio.formstore::mail.login`
* `save_warning` - [checkbox] if enabled, the user will be reminded to save content regularly

The component will display the email login form and -- if the user is logged in -- displays the form manager. Note that if you activate embedded mode, only the form will be rendered. To provide a logout button, use the AJAX framework to query ``formManager::onLogout``.

### The Countdown component

The countdown provides information about the opening and closing time of a form. Its only property is the `form` and can be integrated into any CMS page. It provides the following information (accessible via ``__SELF__`` in the plugin markup):

- `open` - whether the form is currently open for submissions
- `opens` - distance to the form's opening time with `opens.days`, `opens.hours`, `opens.minutes`
- `closes` - same as `opens` but for closing time
- `liftoff` - contains the closing date if the form is open and the opening date otherwise

### Configuring the forms

The available forms can be managed in the plugins backend under ``Stored forms``.

It is important to understand that the plugin does not provide own form definitions or database models. Instead, existing models are used to define a form. To create your custom form, it is recommended to use the [Builder plugin](http://octobercms.com/plugin/rainlab-builder). Please refer to its documentation to learn how to create models and form definitions. Please note that not all backend widgets are supported. Moreover, the file upload widget has currently some limitations, i.e. insufficient validation.

**Important**: The model has to allow mass assignment for the fields that are present in the form. Refer to the [model documentation](https://octobercms.com/docs/database/model#mass-assignment) for more information.

**! WARNING !** Never specify any system models that could be used to compromise the system.

To create a form, the following fields are required:

* *Title* - The form title
* *Model* - The namespace path of the model that will be used to store the data, e.g. `Rainlab\Blog\Models\Post`.
* *Fields* - Relative or absolute path to the fields YAML config file e.g. `formstore_fields.yaml` or `$\Rainlab\Blog\Models\Post\fields.yaml`

There are the following optional settings:

* *Maximum submissions per user* - Allows restricting the number of submissions per user. Set to `-1` for unlimited submissions.
* *Opening and closing time* - Allows specifying a time range in which submissions will be allowed.
* *Introduction* - An optional text with information or instructions for the form, that will be displayed to the user
* *Terms and conditions* - An optional field to provide T&C the user has to agree to if starting a submission.
* *Validation* - Optional OctoberCMS validation rules. If specified, the form cannot be submitted before the validation is passed.

### Relations

If the form model defines relations, they can be activated under the `Relations` tab. To add a relation, another form with the related model has to be created. For example, if the specified form model is `Rainlab\Blog\Models\Post` that has a defined model relation `comments` there has to be another form, e.g. `Comment form`, with the related model, e.g. `Vendor\MyRelatedModel\Comments`. The relation can then be configured by selecting `Comment form` as form and `comments` as relation. Furthermore, a title and required minimum can be specified. As a result, the form manager will allow the user to add comments when working on a post and might require a minimum number of comments before the post can be submitted.

### Activating forms in the manager

In order to work, the configured form has to be activated in a manager's `forms` option in a CMS page.

### Viewing submissions and submitters

The backend also allows to view and manage submissions and submitters. Note that is only possible to view but not manipulate submissions. It is, however, very easy to add an edit functionality for the model using the mentioned `Builder plugin`.

### Events

It is possible to hook into the submission cycle at the following events:

- `nocio.formstore.withdraw`(`$submission`) - when a submission is withdrawed
- `nocio.formstore.submit`(`$submission`): - when a submission is submitted
- events to override the authentication method - see below

The events can, for instance, be used to send an email notification when a user submits a form:

    Event::listen('nocio.formstore.submit', function($submission) {
        Mail::sendTo('hello@demo.com', 'nocio.blog::mail.submission_notice', [
            'submission' => $submission
        ]);
    });

### Custom authentication methods

It is possible to override the default authentication mechanism and use other options instead. In the following example, the ``Rainlab.User`` plugin will be used to authenticate the user. 

First it is necessary to provide a custom submitter object with the following (duck-typed) properties:

- `email` property - The email of the submitter
- `submissions` relation - hasMany model relation to ``Nocio\FormStore\Models\Submission``

In the case of the User plugin, we can simply extend the User model, which already provides the email field

    \Rainlab\User\Models\User::extend(function($model) {
        $model->hasMany = [
            'submissions' => [
                'Nocio\FormStore\Models\Submission',
                'key' => 'submitter_id'
            ]
        ];
    });

Next, we have to override the authentication methods, that return the current submitter/user object and its state. The event has to return an array with the user object and the current authentication state (``true`` for logged-in), e.g.

    /**
     * Authenticates the user
     * @return array[ (object) submitter, (boolean) authenticated ]
     */
    Event::listen('nocio.formstore.authenticate', function($alias) {
        if ($user = Auth::getUser()) {
            return [$user, true];
        } else {
            return [$user, false];
        }
    });

Finally, it is possible to adjust the logout function ...

    /**
     * Log the user out
     */
    Event::listen('nocio.formstore.logout', function($alias) {
        Auth::logout();
        return true;
    });

... and the behaviour when the authentication was unsuccessful, e.g. redirect the user to the login page:

    Event::listen('nocio.formstore.not_authenticated', function($alias) {
            return Redirect::to('/intranet');
    });

Note, that the events take the alias of the formManager component as a parameter, allowing you to override the authentication for specific manager instances.

### Support & Contribution

I can only offer limited support but will try to answer questions and feature requests on [GitHub](https://github.com/nocio/oc-formstore-plugin). I am also happy to accept pull requests, especially for the missing features list on the Plugin details page.

**Please consider donating to support the ongoing development if you find this plugin useful.**

### Acknowledgements

Thanks to the [Uploader plugin](https://github.com/responsiv/uploader-plugin) which code base was adapted to implement the file upload of the form manager.
