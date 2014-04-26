Mikulas/nette-google
====================

This library is heavily inspired by https://github.com/Kdyby/Facebook

Requirements
------------

Mikulas/nette-google requires PHP 5.4 or higher with cUrl extension enabled.

- [Nette Framework 2.0.x](https://github.com/nette/nette)


Installation
------------

The best way to install Mikulas/nette-google is using  [Composer](http://getcomposer.org/):

```sh
$ composer require mikulas/nette-google:@dev
```

With Nette ~2.1 the extension can be enabled in the config file as follows:

```yml
extensions:
	google: Mikulas\Google\DI\GoogleExtension
```

If you're using stable Nette, you have to register it in `app/bootstrap.php`

```php
Mikulas\Google\DI\GoogleExtension::register($configurator);

return $configurator->createContainer();
```

Minimal configuration
---------------------

This extension creates new configuration section `google`.

```yml
google:
	clientId: "612360283240.apps.googleusercontent.com"
	clientSecret: "sqKWXNodBrP30Q_4mwFonAF5"
	apiKey: "AIzaSyA1mhOsAQfvYjWqAokrbutkX20daRqyQZQ"
```

Authentication
--------------

```php
use Mikulas\Google\Google;
use Mikulas\Google\Dialog\LoginDialog;
use Nette\Diagnostics\Debugger;

class LoginPresenter extends BasePresenter
{

	/** @var Google */
	private $google;

	/** @var Users */
	private $users;

	/**
	 * You can use whatever way to inject the instance from DI Container,
	 * but let's just use constructor injection for simplicity.
	 *
	 * Class UsersModel is here only to show you how the process should work,
	 * you have to implement it yourself.
	 */
	public function __construct(Google $google, UsersModel $users)
	{
		parent::__construct();
		$this->google = $google;
		$this->users = $users;
	}

	protected function createComponentGoogleLogin()
	{
		/** @var GoogleLoginDialog $dialog */
		$dialog = $this->google->createLoginDialog();
		$dialog->onResponse[] = function(GoogleLoginDialog $dialog)
		{
			$newUser = FALSE;
			try
			{
				$google = $dialog->getGoogle();
				$me = $google->getIdentity();

				$userEntity = $this->users->getByGoogleId($me['sub']);
				if (!$userEntity)
				{
					$userEntity = $this->orm->users->getByEmail($me['email']);
				}
				if (!$userEntity)
				{
					$newUser = TRUE;
					$userEntity = new User();
					$this->users->attach($userEntity);
					$userEntity->name = $me['name'];
					$userEntity->email = $me['email'];
				}
				$userEntity->googleId = $me['sub'];
				$userEntity->googleAccessToken = $google->getAccessToken();

				$this->user->login(new Identity($userEntity->id, [], $userEntity));
			}
			catch (FacebookApiException $e)
			{
				// TODO handle error
			}

			$this->redirect('this');
		};

		return $dialog;
	}
```

Link to login
-------------

```latte
<a n:href="googleLogin-open!">Login using Google</a>
```
