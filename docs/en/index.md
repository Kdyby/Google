Quickstart
==========

This addon adds support for oauth connection to Google,
so you can seamlessly integrate your application with and provide login through Google.

You can also communicate with Google's api through this addon.



Installation
-----------

The best way to install Kdyby/Google is using  [Composer](http://getcomposer.org/):

```sh
$ composer require kdyby/google:~0.1
```

With Nette 2.1 and newer, you can enable the extension using your neon config.

```yml
extensions:
	google: Kdyby\Google\DI\GoogleExtension
```



Minimal configuration
---------------------

This extension creates new configuration section `google`, the absolute minimal configuration is app ID and secret.
You also might wanna provide default `returnUri`.

```yml
google:
	clientId: "2d93a3e822b6-ac8e06fdb68ac8e06fda221e05cfe042.apps.googleusercontent.com"
	clientSecret: "5A7979_ed14aff7f9bc0a_dC"
	returnUri: ':Forum:Categories:'(do=login-google-response)
```

The "problem" with Google oauth is that it's brutally strict. You have to have specifically defined endpoint and it won't redirect to anything else.
The Github or Facebook just redirects to almost everything you give it but not Google, so what now?
 
The solution to that is the `returnUri`. This configuration value should be a standard Nette presenter path and parameters in the backets.
In the example you can see I'm specifying the presenter `:Forum:Categories:` with `default` action and the signal `login-google-response`,
because that's the signal of the UI component that handles the communication.

Magic happens in the component `login-google`, it stores the request before it redirects to the Google Oauth endpoint, and restores it after user is redirected back.
This behaviour simulates the on-page sign in, that you should be able to do using signals. Image you're reading a forum thread and you're not signed in.
Now you wanna write something and you have to sign in for that. Without this behaviour, you will be redirected to some endpoint of the application that has no context of where have you been.
So you'll end up either on homepage or the programmer has to handle this manually, which is annoying and unnecessary, because it's already handled by the UI component.
That said, you can still handle everything manually or override just parts of the mechanism.

Keep in mind that the component handles security and you should not change the behaviour if you're not 100% sure you understand the Google OAuth.



Debugging
---------

The extension monitors all the api communication, when in debug mode. All that information is available in Tracy panel

![panel](https://raw.githubusercontent.com/Kdyby/Google/ec32e3a3e0ccaf518061ddb5bb84ef54366d35cd/docs/en/panel-screenshot.png)

