# CakeAssets Plugin for CakePHP 2.x
A CakePHP 2.x Plugin to use with managing CSS and JS assets. It will take all of the assets added using the `$this->Html->script()` and `$this->Html->css()` methods and combine each into their own singular, minfied file.

## Requirements
* CakePHP 2.x
* PHP 5.2.8+

# Features
* Combines all the local CSS and JS files into their own minimized single files

# Table of Contents
* [Installation](#installation)
* [Usage](#usage)

# Installation
To install the plugin, place the files in a directory labelled "CakeAssets/" in your "app/Plugin/" directory.

Then, include the following line in your `app/Config/bootstrap.php` to load the plugin in your application.
```
CakePlugin::load('CakeAssets');
```

## Git Submodule
If you're using git for version control, you may want to add the **CakeAssets** plugin as a submodule on your repository. To do so, run the following command from the base of your repository:
```
git submodule add git@github.com:jamiebclark/CakeAssets.git app/Plugin/CakeAssets
```

After doing so, you will see the submodule in your changes pending, plus the file ```.gitmodules```. Simply commit and push to your repository.

To initialize the submodule(s) run the following command:
```
git submodule update --init --recursive
```

To retrieve the latest updates to the plugin, assuming you're using the ```master``` branch, go to ```app/Plugin/CakeAssets``` and run the following command:
```
git pull origin master
```

If you're using another branch, just change "master" for the branch you are currently using.

If any updates are added, go back to the base of your own repository, commit and push your changes. This will update your repository to point to the latest updates to the plugin.


# Usage
In order to use the Plugin, include the `Asset` folder with whatever Controller you'd like to use it with (it may be simplest to include it with `AppController`
```
public function AppController extends Controller {
  $helpers = ['CakeAssets.Asset'];
}
```

Continue using `$this->Html->css()` and `$this->Html->script()` methods to add assets. 

In your layout, use the `$this->Asset->output()` method to output the combined styles:
```
<html>
  <head>
    <?php 
    /** 
     * @param $inline Whether we should output or wait until fetch
     * @param $repeat Should we skip assets that have already been repeated?
     * @param $types Specify specific asset types to be outputted
     **/
    echo $this->Asset->output(true, false, 'css');
    ?>
  </head>
  <body>
    ...
    <?php
    // Add all JS files at the bottom of your code
    echo $this->Asset->output(true, false);
    ?>
  </body>
</html>
