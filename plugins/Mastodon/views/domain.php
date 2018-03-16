<?php if (!defined('APPLICATION')) exit();

echo '<div class="Connect">';
echo '<h1>', $this->data('Title'), '</h1>';

// Post this form back to our current location.
$path = htmlspecialchars(Gdn::request()->path());

echo $this->Form->open(['Action' => url($path), 'onSubmit' =>'return onSubmit()', 'Method' => 'get']);
echo $this->Form->errors();

if ($this->data('Error')) {
    echo '<div class="padded alert alert-warning">';
    echo $this->data('Error');
    echo '</div>';
}

?>
    <div>
        <ul>
            <li>
                <?php
                echo $this->Form->label('Enter Your Mastodon Domain', 'domain');
                echo $this->Form->textBox('domain');
                ?>
            </li>
        </ul>
        <div class="Buttons">
            <?php
            echo $this->Form->button('Auth');
            echo $this->Form->hidden('target');
            ?>
        </div>
    </div>
<?php
echo $this->Form->close();
echo '</div>';
?>

<script type="text/javascript">
var domain_check = false;
function saveLastDomain(domain) {
    localStorage.setItem('last_checked_domain', domain);
}
function getLastDomain() {
    return localStorage.getItem('last_checked_domain', false);
}
function checkMstdnDomain(domain) {
    $.ajax({
        url: "https://"+domain+"/api/v1/instance",
        cache: false,
        success: function(json) {
            if (json.uri) {
                saveLastDomain(json.uri)
            }
            domain_check = true;
            document.querySelector('#Form_domain').form.submit();
        },
        error: function() {
            alert("Invalid domain.");
        }
    });
}
function onSubmit() {
    enter_domain = document.querySelector('#Form_domain').value;

    if (!domain_check) {
        checkMstdnDomain(enter_domain);
        return false;
    } else {
        return true;
    }

}
function onLoad() {
    last_domain = getLastDomain();
    if (last_domain) {
        document.querySelector('#Form_domain').value = last_domain;
    }
}

onLoad();
</script>
