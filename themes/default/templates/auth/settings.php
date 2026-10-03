<?php
// Separate override point; profile markup shares the existing settings contract.
echo $this->partial('auth/profile', get_defined_vars());
