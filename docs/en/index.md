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

The solution to that is the `returnUri`. This configuration value should be a standard Nette presenter path and parameters in the brackets.
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



Authentication
--------------

The Google login sustains of several HTTP redirects,
and as you might have noticed, those don't fit well into PHP objects.

Then how are we going to login to Google you may ask? Easily! There is a component for that!

```php
class LoginPresenter extends BasePresenter
{

	/** @var \Kdyby\Google\Google */
	private $google;

	/** @var UsersModel */
	private $usersModel;

	/**
	 * You can use whatever way to inject the instance from DI Container,
	 * but let's just use constructor injection for simplicity.
	 *
	 * Class UsersModel is here only to show you how the process should work,
	 * you have to implement it yourself.
	 */
	public function __construct(\Kdyby\Google\Google $google, UsersModel $usersModel)
	{
		parent::__construct();
		$this->google = $google;
		$this->usersModel = $usersModel;
	}


	/** @return \Kdyby\Google\Dialog\LoginDialog */
	protected function createComponentGoogleLogin()
	{
		$dialog = new \Kdyby\Google\Dialog\LoginDialog($this->google);
		$dialog->onResponse[] = function (\Kdyby\Google\Dialog\LoginDialog $dialog) {
			$google = $dialog->getGoogle();

			if (!$google->getUser()) {
				$this->flashMessage("Sorry bro, google authentication failed.");
				return;
			}

			/**
			 * If we get here, it means that the user was recognized
			 * and we can call the Google API
			 */

			try {
				$me = $google->getProfile();

				if (!$existing = $this->usersModel->findByGoogleId($google->getUser())) {
					/**
					 * Variable $me contains all the public information about the user
					 * including Google id, name and email, if he allowed you to see it.
					 */
					$existing = $this->usersModel->registerFromGoogle($google->getUser(), $me);
				}

				/**
				 * You should save the access token to database for later usage.
				 *
				 * You will need it when you'll want to call Google API,
				 * when the user is not logged in to your website,
				 * with the access token in his session.
				 */
				$this->usersModel->updateGoogleAccessToken($google->getUser(), $google->getAccessToken());

				/**
				 * Nette\Security\User accepts not only textual credentials,
				 * but even an identity instance!
				 */
				$this->user->login(new \Nette\Security\Identity($existing->id, $existing->roles, $existing));

				/**
				 * You can celebrate now! The user is authenticated :)
				 */

			} catch (\Exception $e) {
				/**
				 * You might wanna know what happened, so let's log the exception.
				 *
				 * Rendering entire bluescreen is kind of slow task,
				 * so might wanna log only $e->getMessage(), it's up to you
				 */
				\Tracy\Debugger::log($e, 'google');
				$this->flashMessage("Sorry bro, google authentication failed hard.");
			}

			$this->redirect('this');
		};

		return $dialog;
	}

}
```

And in template, you might wanna render a link to open the login dialog.

```smarty
{* By the way, this is how you do a link to signal of subcomponent. *}
<a n:href="googleLogin-open!">Login using google</a>
```

Now when the user clicks on this link, he will get redirected to google app authentication page,
where he can allow your page or decline it. When he confirms the privileges your application requires,
he will be redirected back to your website. Because we've used Nette components,
he will be redirected to a signal on the `LoginDialog`, that will invoke the event
and your `onResponse` callback will be invoked. And from there, it's child play.
