# Technical Debt

- **Flex recipe doesn't copy `resources/scripts/`.** The recipe's
  `copy-from-recipe` only handles `config/`. `resources/scripts/opensign-minio-patch.js`
  still requires running `opensignb:install` manually. Extending the recipe
  (a new PR to symfony/recipes-contrib) would let Flex copy it too.
