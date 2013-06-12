This zip file should contain three files.

1. readme.txt (that's what you're reading!)
2. index.php
3. .htaccess (careful this file is usually hidden)

In order to use this redirector script you'll need the following:

1. Your old server needs to be running php.
2. Your old server needs to be running on apache.
3. Your old server needs to have mod_rewrite enabled.

If you don't know whether or not your old server satisfies these requirements that's okay. Try using the script and if it doesn't work at all then you'll know you don't satisfy the requirements and may have to contact someome more technical to help you out.

To use this redirector what you need to do is.

TEST FIRST!!!

1. Create a folder on your server for testing. Call it something like forumtest. You can usually do this with your ftp program.
2. Edit index.php with the location of your new forum.
3. Drop these three files into the folder. Again, this can be done with ftp.
4. Browse to some test urls that are similar to your old forum, but in the new directory. index.php has a lot of comments that should help you build a redirector.

YOUR TESTS WORKED!!!

1. You want to go to this stage once your new forum is ready to go and your old forum is ready to be shut down.
2. Shut down your old forum.
3. Import your data into your new forum.
4. Look in the folder of your old forum. If you see an index.php then back it up somewhere. Always backing up is just good practice.
5. Copy the three files from your test into your old forum's folder.

YOUR TESTS DIDN'T WORK

1. There is some debugging help in index.php.
2. If you still can't get your redirects to work then you need to get some technical support for this. Setting up redirects isn't rocket surgery, but there are also many things that can go wrong.
3. Don't panic. The web was made to work so there is a solution out there and it most-likely doesn't require robots, time machines or lazers.