AvS_FastSimpleImport
=====================
Wrapper for Magento ImportExport functionality which imports data from arrays

[Documentation](http://avstudnitz.github.io/AvS_FastSimpleImport/)
-------------------------------

Facts
-----
- version: 0.7.0
- extension key: AvS_FastSimpleImport
- extension on Magento Connect: n/a
- Magento Connect 1.0 extension key: n/a
- Magento Connect 2.0 extension key: n/a
- Composer name: avstudnitz/fast-simple-import (included in http://packages.firegento.com/)
- [Extension on GitHub](https://github.com/avstudnitz/AvS_FastSimpleImport)
- [Direct download link](https://github.com/avstudnitz/AvS_FastSimpleImport/tarball/master)
- [Documentation](http://avstudnitz.github.io/AvS_FastSimpleImport/)

Description
-----------
This module allows to import from arrays and thus using any import source, while the Magento module only imports from files. 
ImportExport exists since Magento 1.5 CE / 1.10 EE, image import since 1.6 CE / 1.11 EE. Thus, this module needs at least 
one of those versions.
ImportExport has a special import format. See [specifications](https://www.integer-net.com/importing-products-with-the-import-export-interface/) about the expected format.

Main Features:

- Fast Import using the built in Magento module ImportExport
- Partial Indexing of only the imported products available
- Unit tests for many test cases included
- Importing of additional entities: Categories, Category-Product relations
- Improved error messages stating which values are expected instead of a given value

Please see our [**documentation**](http://avstudnitz.github.io/AvS_FastSimpleImport/) for a full list of features.

Requirements
------------
- PHP >= 5.3.0
- Mage_ImportExport

Compatibility
-------------
- Magento >= 1.6.0.0 / Magento EE >= 1.11.0.0

Installation Instructions
-------------------------
1. Install the extension via GitHub, composer or a similar method.
2. If you are using the Enterprise Edition then add ```<Enterprise_ImportExport/>``` to the ```<depends>``` node directly after ```<Mage_ImportExport/>```
3. Clear the cache, logout from the admin panel and then login again.
4. Read the [documentation](http://avstudnitz.github.io/AvS_FastSimpleImport/)
5. Configure the extension at `System -> Configuration -> Services -> FastSimpleImport`.

Uninstallation
--------------
1. Remove all extension files from your Magento installation

Support
-------
If you have any issues with this extension, open an issue on [GitHub](https://github.com/avstudnitz/AvS_FastSimpleImport/issues).

Contribution
------------
Any contribution is highly appreciated. The best way to contribute code is to open a [pull request on GitHub](https://help.github.com/articles/using-pull-requests).

Lead Developers
---------
Andreas von Studnitz
http://www.integer-net.de/agentur/andreas-von-studnitz/
[@avstudnitz](https://twitter.com/avstudnitz)

Paul Hachmang
http://www.h-o.nl/
[@paales](https://twitter.com/paales)


License
-------
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)
