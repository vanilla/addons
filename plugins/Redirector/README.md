
### The Pocket which help redirect invalid URL##

```
Example:
https://thisthing.thatthing.com/#/something?nothing=id
```

When there is a HashTag in the Url like the example, The custom redriector plugin or routes will not work . We need to create a pocket for it and also route.This way it will work .

----
```
pocket to url which have #Tag
<script type="text/javascript">
    var urlStr= window.location.hash;
    
    if (urlStr.substring(0,2) == "#/"){
        window.location.assign(urlStr.substring(2));
    }else {
        window.location;
    }

</script>

Config in the pocket 
---

set Page -> (All)
location -> Head
repeat -> efore

```