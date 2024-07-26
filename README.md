# 🚨🚨 This repository is no logner maintained 🚨🚨

This code started as a component back in the Joomla! 1.6 development days to demonstrate the exponential slowness experienced adding new articles back then. It helped create massive amoutns of contents which helped people improve Joomla!.

Between then and 2019 I was using this code to generate large amounts of (random) content to simulate a worst case situation in my development site.

By 2019 I made a different kind of dev site which was a far better approximation of a real-world site. I also adapted these scripts and eventually created Joomla! console plugins to generate even better structured large amounts of random content, including content for my extensions such as Engage. This repository has not been updated since. Five years later, I decided to put it out of its misery.

So long, Overload!. You served us well. 🫡

# Overload!

Mass Joomla! 3 and 4 sample content creator

## Executive summary

This script will create a large amount of content to simulate a busy real world site. This is useful for developing Joomla extensions or Joomla itself. 

You can configure how many categories and articles to create, as well as where the content will be placed into. The text for category descriptions and article contents is dynamically generated random text ("Lorem ipsum"). 

**WARNING!** This script is only meant to be run on sites you are ready to throw away and start over. Using its default configuration will overwrite all of your site's content. If you're not careful you can completely trash a site. If unsure, take a backup before using this script, e.g. using [Akeeba Backup](https://extensions.joomla.org/extension/akeeba-backup/).

## Copyright notice

Overload!
Copyright (C) 2011-2020 Nicholas K. Dionysopoulos

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see [the on-line version](http://www.gnu.org/licenses/).

The full text of the license can be found in the LICENSE.txt file.

## Documentation

Consult the help.txt file.

## Requirements

* Joomla! 3.9 or 4.0
* At least PHP 7.1 (for Joomla 3.9) or 7.2 (Joomla 4.0). Tested with PHP 7.1, 7.2, 7.3 and 7.4.
* PHP `memory_limit` at least 32MB

## Support and collaboration

If you spotted a bug please file an issue on the GitHub repository.

I would appreciate being kind and including as much information as possible. I want to be able to work with you, not against or despite you. Thank you for your understanding.
