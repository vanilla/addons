# Welcome Post
This plugin prompts users to create a first post to introduce themselves to the community by bringing them to a Discussion creation form with the title pre-populated with "Hi, my name is {username}". The user can skip over the form or fill it in.

## Features

### Creates a category called Welcome

When the plugin is turned on, if there isn't a welcome category, it is created.

### Only works on confirmed users

If a forum requires email confirmation of registrants this step is skipped and is not available when the registrant confirms with email later.

### Contains translatable text

Predefined text can be altered in locale files.
 
* **Instruction**: `WelcomePostInstruction` defaults to Say "'Hello' to the rest of the community. If you're feeling too shy, press 'Skip'"
* **Default Post Title**: `Welcome post discussion name` defaults to "Hi, my name is %s!" where %s is the username.
* **Default Post Body**: `WelcomePostBody` defaults to blank because not every forum will want to add a body for the users. They can optionally add `WelcomePostBody` to their locale file.

     