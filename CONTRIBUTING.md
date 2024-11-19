## PHPStan indexing/analysis in PhpStorm

PhpStorm might have issues indexing PHPStan due to .phar. To fix this, follow the steps below:

1. **Open the Project in PhpStorm**.

2. **Locate the `.idea/php.xml` file** in your project directory.

3. **Edit the `php.xml` file** to include the path to `phpstan.phar`:

    Add the following path inside the `<include_path>` section:
    
    ```xml
    <path value="$PROJECT_DIR$/vendor/phpstan/phpstan/phpstan.phar" />
    ```
