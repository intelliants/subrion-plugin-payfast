<form action="{$payfast_host}" method="post">
{foreach $payfast as $key => $value}
	<input name="{$key}" value="{$value}">
{/foreach}
</form>