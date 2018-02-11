# README

## world-geo-parser

This is a script you can use to extract geographical information from www.geonames.org, one of the largest geographical databases available in the internet.  
The geonames's database is available in zip and txt files and provides an extensive array of useful geographical information. Unfortunately if you only need data such as states and cities of each country in the world, this database is going to look a bit unorganized and big for your needs. This script is an attempt to organize this information in a better fashion, and to only retrieve the countries, states/provinces and cities.  
  
I recommend you also take a look to the angular module I wrote to see how you can use this information in your web applications. [https://github.com/gabrielclavero/gc-geo-fields](https://github.com/gabrielclavero/gc-geo-fields)  
  
  
After running the script you will have the following files and folders in your computer:  
  
- countries.json: that contains the names and 2-letter iso codes of all the countries in the planet.  
- countries folder: in this folder you will find a json file for each country in the world, containing all the states/provinces within it.  
- states folder: in this folder you will find a json file for each state/province of each country in the world, containing the cities within it.  
  
## IMPORTANT

Keep in mind after running the script some of the states will have TONS of cities on it (up to 11k maybe) Maybe what you want instead are the second-order administrative divisions of each country. To do that, use the feature class 'A' and feature code 'ADM2' to gather the data on step 3 of the script. 
  
## LICENSE
  
The Geonames's database is licensed under a Creative Commons Attribution 3.0 License and so is this script.  
[http://creativecommons.org/licenses/by/3.0/](http://creativecommons.org/licenses/by/3.0/)