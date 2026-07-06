
Copyright (C) 2006 Steven Peterson
Congruency is free software, licensed under the GNU GPLv2 or later.
See the LICENSE file in the project root for full license terms.


Overview:


	The Persistant Object Manager (POM) is for grouping all persistant program data, into a single composite object. 

	Understanding the Persistant Object Manager:


	
What are the Objectives:
	

	When an object is instantiated, the class loader identifies the class file to load on demand.
	Many of these objects contain data that can be used throughout the session, and it's beneficial to store this data
	in the session. 

unserialization of objects between page reloads
			
	partitions the session, a first step in creating a user space 













Static variables are lost between page loads, this will need to be reset on every page reload. 
---Also isset(self::pOM) does not seem to work. 
The coupling between this module and the class loader are high. - the class loader needs to exist before
the POM yet I want the class loader inside of the POM. Ideally destroying the POM should result in a new session.   
Focing a new class loader to be instantiated. 























*/
