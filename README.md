# Campaign monitor list deleter

Delete campaign monitor lists that haven't been used or updated in three months

## Set up

1. Run ```composer install```
2. Copy ```config/local.dist.php``` to ```config/local.php```, then fill in the client ID and auth token values

## Running the script

1. Run ```$ php bin/delete.php``` to view the lists that would be deleted, this is a dry-run mode
2. Run the script again with the '--delete' flag to actually delete these lists