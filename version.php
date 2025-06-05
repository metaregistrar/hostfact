<?php
$version['name']            = "Metaregistrar";
$version['api_version']     = "6.x";
$version['date']            = "2025-06-01"; // Last modification date
$version['version']         = "1.2"; // Version released for WeFact
$version['autorenew']       = true; // AutoRenew is default?  true | false
$version['handle_support']  = true; // Handles are supported? true | false
$version['cancel_direct']   = true; // Possible to terminate domains immediately?  true | false
$version['cancel_expire']   = true; // Possible to stop auto-renew for domains? true | false

// Information for customer (will be showed at registrar-show-page)
$version['dev_logo']		= 'https://metaregistrar.com/logo.png'; // URL to your logo
$version['dev_author']		= 'Metaregistrar BV'; // Your companyname
$version['dev_website']		= 'https://www.metaregistrar.com'; // URL website
$version['dev_email']		= 'support@metaregistrar.com'; // Your e-mailaddress for support questions
$version['dev_phone']		= '+31.85 888 5692'; // Your phone number for support questions

// Does this registrar integration support functions related to domains?
$version['domain_support']  = true;
// Does this registrar integration support functions related to SSL certificates?
$version['ssl_support']   	= false;

// Does this registrar integration support functions related to DNS management?
$version['dns_management_support']   	= true;
// Does this registrar integration support DNS templates?
$version['dns_templates_support']       = false;
// Does this registrar integration support DNS records?
$version['dns_records_support']         = true;
?>