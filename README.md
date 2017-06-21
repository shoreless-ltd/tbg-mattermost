# The Bug Genie Mattermost integration module

## Description

This is an integration module for [The Bug Genie](https://github.com/thebuggenie/thebuggenie)
issue tracker. It allows posting information about TBG events to your
[Mattermost](https://about.mattermost.com) team channels.  

You can setup the integration on a per-project basis. The following events are supported at
the time being:  

  * Creation of an issue
  * Commenting to an issue
  * Creation of new releases
  

## Requirements

  * A running [The Bug Genie](https://github.com/thebuggenie/thebuggenie) installation, v4.2+
  * PHP 5.6+
  * [Composer](https://getcomposer.org/doc/00-intro.md)


## Installation

REMARKS: Replace `thebuggenie` within the examples with your path to your The Bug Genie installation.  


### 1: The Bug Genie Module Installation

Clone this repository either straight into a folder under `thebuggenie/modules/mattermost`,
download the sources and extract them to `thebuggenie/modules/mattermost`, or symlink the
sources to the same folder (IMPORTANT: The folder name under thebuggenie/modules MUST be
`mattermost`, as The Bug Genie requires the module folder to match the module name):  

<pre>
cd thebuggenie/modules
git clone git@github.com:shoreless-ltd/tbg-mattermost.git mattermost
</pre>


### 2: Install Composer Dependencies

This module uses the composer packages, which must be installed after you
installed the module to The Bug Genie.  

Navigate to the `thebuggenie/modules/mattermost` folder and install the
composer dependencies:  

<pre>
cd thebuggenie/modules/mattermost
composer install
</pre>


### 3: Activate the Module

You can now enable the module from the configuration section in The Bug Genie.  


### 4: Per-Project Settings

After activating the module, head over to the settings of the project you like
to integrate into your Mattermost channel. Under '''Other Project Details >
Mattermost integration''' you can setup your Mattermost webhook URL and
channel and decide which TBG events should be announced to your channel.  

To create a webhook URL for your Mattermost team channel, please follow the
guides in the [Mattermost Developer Docs](https://docs.mattermost.com/developer/webhooks-incoming.html#creating-integrations-using-incoming-webhooks).  


## Roadmap

### Planned Features

  * Add further issue fields to issue creation notice.
  * Add issue fields/status change notices.
  * Add translation support for posting to Mattermost channels in a different
    language than en_US.

### Suggested Features

  * _Add slash commands support to fetch issue details, or
    trigger issue field updates and workflow transitions._


## Reporting issues / feature requests

If you find any issues or have a suggestion for further development, please
report them in the GitHub issue tracker:  
https://github.com/shoreless-ltd/tbg-mattermost/issues
