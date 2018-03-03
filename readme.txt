=== DGXPCO ===
Contributors:      ericmann
Donate link:       https://paypal.me/eam
Tags:              secure, update, upgrade
Requires at least: 4.2.0
Tested up to:      4.9.4
Stable tag:        1.2.0
License:           MIT
License URI:       https://opensource.org/licenses/MIT

Secure software updates for WordPress.

== Description ==

DGXPCO (Digital Guarantees for eXplicitly Permitted Core Operations) is a proof-of-concept cryptographic signature verification utility for WordPress software updates. The plugin will source manual (offline) signatures for WordPress core updates and prevent the application from updating unless the contents of the update payload are verified with a remote signature.

This provides a _second_ source of truth for the integrity of WordPress updates beyond the MD5 content hash supplied in the header from the WordPress update server. If that server were ever breached, it's unlikely the server hosting the _signatures_ of the files was also breached. If the signatures ever fail to validate, you can know your site was protected from an attack.

== Installation ==

= Manual Installation =

1. Upload the entire `/dgxpco` directory to the `/wp-content/plugins/` directory.
2. Activate DGXPCO through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= Who is responsible for the signatures =

At the moment, [Eric Mann](https://eamann.com) will personally verify and sign every new update payload once it's released by the core team. The signatures of each core file are hosted in a [separate GitHub repository](https://github.com/DisplaceTech/release-hashes), with every commit signed by Eric's [GPG private key](https://keybase.io/eamann) for redundant verification.

== Screenshots ==

None at this time

== Changelog ==

= 1.2.0 =
* Filter the upgrade cache to avoid prompting core upgrades with missing signatures.

= 1.1.0 =
* Introduce integration test for full core compatibility guarantees.

= 1.0.0 =
* First release

== Upgrade Notice ==

= 1.1.0 =
The minimum WordPress version requirement is now 4.2.0.

= 1.0.0 =
First Release
