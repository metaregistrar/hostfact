# Metaregistrar Wefact
Wefact implementation for Metaregistrar domain requests

Usage: 
- Run Wefact on your own system
- Copy all code in this repository to Pro/3rdparty/domain/metaregistrar
- In Wefact, select "metaregistrar" as new registrar
- Enter your EPP-login username and password from Metaregistrar
- Whitelist the IP address where your Wefact service runs within the Metaregistrar back-end

_Please note: If you encounter any issues with this implementation, please report bugs via the "issues" option_

This module supports domain name registrations and dns maintenance

TODO:
- Add glue records when hostnames match the domainname
