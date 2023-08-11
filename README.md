<p align="center"><img src=".github/header.png"></p>

# Saloon SDK Generator

This package provides a convenient way to generate a PHP SDK using the [Saloon](https://docs.saloon.dev/) package based
on a Postman Collection JSON file (v2.1). With this tool, you can quickly transform your Postman Collection into a
fully-functional PHP SDK for interacting with APIs.

## Installation

You can install this package using Composer:

```shell
composer require crescat/saloon-sdk-generator
```

## Usage

To generate the PHP SDK from a Postman Collection JSON file, you can use the provided Artisan command:

```shell
php artisan generate:postman POSTMAN_COLLECTION.json --force
```

- `POSTMAN_COLLECTION.json`: Path to the Postman Collection JSON file.
- `--force`: (Optional) Use this flag to overwrite existing files.

## Links and References

- [Postman Collection Format](https://learning.postman.com/collection-format/getting-started/structure-of-a-collection/)
- [Postman Collection Format Schema](https://blog.postman.com/introducing-postman-collection-format-schema/)
- [Importing and Exporting Data in Postman](https://learning.postman.com/docs/getting-started/importing-and-exporting/exporting-data/)

## TODOs


- Implement smart "did the definition change"-feature that can update only what changed in request classes [ref](https://doc.nette.org/en/php-generator#toc-generating-according-to-existing-ones)
- Add: Option to specify connector class name and namespace

## Contributing

Contributions to this package are welcome! If you find any issues or want to suggest improvements, please submit a pull
request or open an issue on the [GitHub repository](link-to-your-repo).

## Credits

This package is built on the shoulders of giants, special thanks to the following people for their open source work that
helps us all build better software! ❤️

- [Nette PHP Generator](https://github.com/nette/php-generator)
- [Nuno Maduro for Laravel Zero](https://github.com/laravel-zero/laravel-zero)
- [Sam Carré for Saloon](https://github.com/Sammyjo20)

## Built by Crescat

[Crescat.io](https://crescat.io/products/) is a collaborative software designed for venues, festivals, and event
professionals.

With a comprehensive suite of features such as day sheets, checklists, reporting, and crew member booking, Crescat
simplifies event management. Professionals in the live event industry trust Crescat to streamline their workflows,
reducing the need for
multiple tools and outdated spreadsheets.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
