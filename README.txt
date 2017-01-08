---------------------------------------------------------------------------
FFBot is a Telegram Bot for querying information about your Freifunk net
---------------------------------------------------------------------------

Telegram is an messaging app and available for Android as well as iOS.
FFBot (and me personally) is not affiliated with Telegram in any ways 
other than using Telegram's messaging app and bot api.

It is presumed that you have the app Telegram installed on your phone.
For interacting with BotFather or the FFBot you can use Telegram app 
or Telegram webinterface.


Querying FFBot is currently done by two methods:

1) directly accessing community's nodes.json
   This was the first approach and enough for simple queries without the 
   need to store further data. This is used for all querries that do not
   deal with "node alarms".

2) periodically import nodes.json to db and then query db
   For the later implemented feature "node alarms" it was necessary to 
   store data, therefore I decided to use a database. 

I have tested only meshviewer's nodes.json as input file as used in our
community Freifunk Westpfalz.

Statistics /stats is currently only working with Freifunk Westpfalz due to
hardcoded node strings. I will change that in future. Until then, remove 
entry from available commands in BotFather.

In future, all queries should be re-written to use the database instead of 
directly accessing nodes.json.

Happy querying!

---------------------------------------------------------------------------

Docs
====
Please see following files for further information:
- ./LICENSE.txt - GPL3 license text
- ./README.txt - this readme file
- ./docs/BOTFATHER.txt - helping you create your Telegram Bot
- ./docs/CHANGELOG.txt - history of versions
- ./docs/CONFIG_FILE.txt - description of config params
- ./docs/FILES.txt - list of all files
- ./docs/INSTALL.txt - installation and update instructions


Issue tracking
==============
Please use GitHub's issue tracking system, if you encounter any issues 
(bugs, feature requests, etc.) related to FFBot.

see https://github.com/Little-Ben/FFBot/issues

Please be aware that I only do this project in my spare time 
and it might take as long as I need :-)


Contributing
============
I am happy about new or improved features and encourage you to contribute to 
FFBot. Please make sure to contribute at GitHub: 

https://github.com/Little-Ben/FFBot

---------------------------------------------------------------------------

(this document's version: 2017-01-08)