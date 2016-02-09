# File Difference Finder for Local & Remote Directories #

### What is this? ###

A utility to find file differences between local & remote in just one load, i used Filezilla File Difference but i had to go through to the entire directory to find the differences so made this utility, it will scan all files & folders under directories that will be mentioned in rule list. You can add some folders to ignore list to increase response time. File difference algorithm is simple and it is not precise enough as it is initial version. Best usage will be when you're working on existing project or unfortunately not taking advantages of any VCS.

P.S: UI ain't good enough, i would appreciate if anyone help me out in UI. Please fork this repo and create a pull request for that.

### Requirement ###
* PHP 5.3 or above.

### Setup Guide ###

* Open `fileDiff-server.php`, Add your folders to scan and ignore variable (make sure path should be relative or full path)
* Upload `fileDiff-server.php` to your accessible server.
* Open `fileDiff-client.php`, Specify your basepath, HTTP URL path of that file which we uploaded `fileDiff-server.php` Add your folders to ignore on local to scan
* Thats it. You're ready to go

### Folder Scan Guide ###
* Wildcard folder scanning supported on both `client` and `server` file.
* `Classes/*.php` will scan each files under `Classes` directory with extension **.php**
* `.txt` will scan **.txt** files under *basePath* directory.

### Ignore Scanning Guide ###
* `*/error_log` will ignore scanning on every directory, but `error_log` will just ignore on *basePath* root.

### TODO :: 7th-Apr-2015 ###
1. Add this revision (*file*) to ignore.
2. Upload/Download selected file(s) to remote.
3. Add multiple comparison type *currently signature base method supported*
