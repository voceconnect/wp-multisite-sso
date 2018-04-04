WP Multisite SSO
==================

Contributors: voceplatforms, smccafferty  
Tags: wp-multisite-sso, sso, login, logout, multisite,
domain mapped, authenticate, authentication  
Requires at least: 4.0  
Tested up to: 4.9.5  
Stable tag: 1.1.1  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

## Description
Single sign on for a multisite WordPress implementation. Users are authenticated for all sites across the network. This plugin is best used within a domain mapped environment where the normal domain authentication cookie would not apply. Uses internal WordPress authentication functions, including maintaining the use of the standard WordPress login page, to authenticate the user across the network.

Settings to customize the SSO login/logout loading page by inheriting the default WordPress login page CSS, custom login page CSS included in theme or even specifying CSS.

**IMPORTANT:** In order for this plugin to work properly, it MUST BE network enabled. The web server also needs to support JSONP requests.
 
## More Information
For more information, please refer to the [wiki](https://github.com/voceconnect/wp-multisite-sso/wiki).
