Laravel Active Directory with Azure and LDAP
This guide will walk you through the steps necessary to create a custom active directory with LDAP integration, using Azure AD DS and Laravel. The following sections will explain the necessary configuration and integration steps in detail.

Prerequisites
Before starting, ensure that you have the following prerequisites installed and configured:

An Azure account

A Laravel application

Composer package manager

# Step 1: Azure AD DS Configuration
Log in to your Azure account and navigate to the Azure portal.
Click on the "Create a resource" button and search for "Azure Active Directory Domain Services".
Follow the prompts to create a new instance of Azure AD DS.
After the instance has been created, navigate to the "Overview" page and note down the domain name and the domain administrator username and password.

# Step 2: Configure secure LDAP for an Azure Active Directory Domain Services managed

You will need a certificate personally i used self-signed certificate

# Create a self-signed certificate for use with Azure AD DS
https://learn.microsoft.com/en-us/azure/active-directory-domain-services/tutorial-configure-ldaps

- After you secured the LDAP server you can go to your Domain Services yourdomain.com and Click properties

You will find Secure LDAP external IP addresses copy the IP address

Go to C:/windows/system32/drivers/etc/hosts 

Make a new connection at the end of the line

(YOUR EXTERNAL IP ADDRESS)  aadds.yourdomain.com

For example

# 1.1.1.1.1  aadds.yourdomain.com

After that install ldp.exe from 

https://learn.microsoft.com/en-us/windows-server/remote/remote-server-administration-tools

Launch ldp.exe go to Connection and use 

aadds.yourdomain.com

Port 636 and enable SSL checkbox

After you connect you should go to 

Connection > Bind > Bind with Credentials

Login with credentials user password and yourdomain.com

If you get an error logging in then you should add the port 636 to firewall to enable LDAPS

# Step 3: Connecting to LDAP with Laravel

Install LdapRecord

composer require directorytree/ldaprecord-laravel
php artisan vendor:publish --provider="LdapRecord\Laravel\LdapServiceProvider"

Install Fortify

composer require laravel/fortify
php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"

# Don't run the migrations yet 

Edit your users migration and make email nullable if you dont want to use email

// database/migrations/2014_10_12_000000_create_users_table.php
$table->string('username')->unique(); // add this line

Run the migrations php artisan migrate

# Step 4: Ldap Record Configuration



// config/auth.php

'guards' => [

    'web' => [
    
        'driver' => 'session',
        
        'provider' => 'ldap', // Changed to 'ldap'
        
    ],
    
],

'providers' => [

    'ldap' => [
    
        'driver' => 'ldap',
        
        'model' => LdapRecord\Models\ActiveDirectory\User::class,
        
        'database' => [
        
            'model' => App\Models\User::class,
            
            'sync_passwords' => false,
            
            'sync_attributes' => [
            
                'name' => 'cn',
                
                'email' => 'mail',
                  
                'username' => 'samaccountname',
                
            ],
            
        ],
        
    ],
    
],


Add the LdapAuthenticatable interface and the AuthenticatesWithLdap trait to your User model.

// app/Models/User.php

use LdapRecord\Laravel\Auth\LdapAuthenticatable;

use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;

class User extends Authenticatable implements LdapAuthenticatable
{

    use AuthenticatesWithLdap;

}


We need to update the fortify configuration to expect the username instead of an email address and we also need to disable other built in features as we don’t want users to register via our app, we want them to log in with an existing active directory account.

// config/fortify.php

'username' => 'username',

'features' => [

    // Features::registration(),
    
    // Features::resetPasswords(),
    
    // Features::emailVerification(),
    
    // Features::updateProfileInformation(),
    
    // Features::updatePasswords(),
    
    // Features::twoFactorAuthentication(),
    
],


Update the AuthServiceProvider by overwriting the Fortify:authenticateUsing method so it expects the samaccountname and password, rather than email.

// app/Providers/AuthServiceProvider.php

public function boot() 
{
   Fortify::authenticateUsing(function ($request) {
   
        $validated = Auth::validate([
        
            'samaccountname' => $request->username,
            
            'password' => $request->password
            
        ]);

        return $validated ? Auth::getLastAttempted() : null;
        
    }); 
    
}
  
  
  #Step 5: Testing
  
Go to your .env variables
 add these
 
LDAP_LOGGING=true

LDAP_CONNECTION=default

LDAP_HOST=127.0.0.1

LDAP_USERNAME="cn=user,dc=local,dc=com"

LDAP_PASSWORD=secret

LDAP_PORT=636

LDAP_BASE_DN="dc=local,dc=com"

LDAP_TIMEOUT=5

LDAP_SSL=true

LDAP_TLS=false

Change them to your needs and update the last config app 
Go to config/ldap.php

'default' => [

            'hosts' => [env('LDAP_HOST', '127.0.0.1')],
            
            'username' => env('LDAP_USERNAME', 'cn=user,dc=local,dc=com'),
            
            'password' => env('LDAP_PASSWORD', 'secret'),
            
            'port' => env('LDAP_PORT', 389),
            
            'base_dn' => env('LDAP_BASE_DN', 'dc=local,dc=com'),
            
            'timeout' => env('LDAP_TIMEOUT', 5),
            
            'use_ssl' => env('LDAP_SSL', false),
            
            'use_tls' => env('LDAP_TLS', false),
            
            'options' => [
            
                LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_NEVER, // add this line (if you are using self signed certificate)
                
            ],
            
# php artisan ldap:test

Once you are successfully logged in with your LDAP credentials 

then you can import users or groups like this

php artisan ldap:import users

or you can do just php artisan ldap:import

if it gives you information that it found users ready to import it will just import

or you can create a login blade and you can login users from there with their active directory credentials

# Enjoy
