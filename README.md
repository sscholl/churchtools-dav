# What is churchtools-dav

churchtools-dav is aiming to be a drop-in CardDAV server for ChurchTools 3. It allows users to synchronize their devices and addressbook with an existing kOOL install. Currently, it is read-only and in development.

### Feature list:

* Supports browsing CardDAV addressbook while honoring user rights in ChurchTools.
* Based on popular [SabreDAV server](http://code.google.com/p/sabredav)
* [Development Notes](http://sabre.io/dav/caldav-carddav-integration-guide/)

### What could become of this ...

* Two-way sync could be implemented with a reasonable amount of work
* Group support could be implemented, either as CardDAV categories or with an entry for each group
* CalDAV could eventually be implemented as well

### Current state

* read-only

### Install

To install, it should be sufficient to drop the contents of this repository right into your main ChurchTools folder.
