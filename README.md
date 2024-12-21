# PHP Photo Gallery
A basic image gallery that doesn't rely on a database. Simply upload your images, in separate folders if preferred, and optionally create .ini metadata files. This is intended to be hosted on shared web hosts that offer PHP support.

![Screenshot](gallery.png)
![Screenshot](lightbox.png)

## Setup
Copy the `gallery.php` and `generate-thumbnails.php` files into your web server's document root.
Create a directory named `gallery` in the same directory as `gallery.php` and `generate-thumbnails.php`.
Place your images in the `gallery` directory.


If you upload a large number of images, consider using the `generate-thumbnails.php` script to generate thumbnails in the background. You can do this by accessing the `generate-thumbnails.php` file in your web browser. The script will generate thumbnails for all images in the `gallery` directory.

Folders of images inside the gallery folder will be treated as albums. You can navigate between albums using the dropdown menu at the top of the page.

![Dropdown menu](dropdown.png)

In each album folder, you can create a `metadata.ini` file to set a custom title for the album. This title will be displayed on the page.

Individual images can also have settings in their albums `metadata.ini` file to set a custom title, caption, and date. This metadata will be displayed in the lightbox when an image is clicked.

## Configuration
You can configure the gallery title in the `config.ini` file.