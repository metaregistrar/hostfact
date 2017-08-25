# Metaregistrar Wefact
Wefact implementation for Metaregistrar domain requests

Usage: 
- Run Wefact on your own system
- Create a directory named 'metaregistrar in /Pro/3rdparty/domain/ and copy all this code to this directory
- Whitelist the IP address where your Wefact service runs within the Metaregistrar back-end
- In Wefact, select "metaregistrar" as new registrar, select domain name and DNS management
- Enter your EPP-login username and password from Metaregistrar

_Please note: If you encounter any issues with this implementation, please report bugs via the "issues" option_

This module supports domain name registrations and dns maintenance

If you want to use Metaregistrar DNS, please make sure your nameservers are set to 

_ns1.yourdomainprovider.net, ns2.yourdomainprovider.net, ns3.yourdomainprovider.net

TODO:
- Add glue records when hostnames match the domainname
