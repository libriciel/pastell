<?php

/**
* @var Gabarit $this
 * @var array $manifest_info
 */

$elapsedTime = round($this->getPastellTimer()->getElapsedTime(), 3);

?>

<br/>
<div id="bottom">

    <div class="bloc_copyright">
        <div class="bloc_logo_libriciel">
            <a href='https://www.libriciel.fr/' target="_blank">
                <img src="img/commun/ls-light.svg" alt="Libriciel" />
            </a>
        </div>
        <div class="bloc_mentions">
            <p> Pastell&nbsp;<?php echo $manifest_info['version'] ?>
                 -
                <a href="https://www.libriciel.fr" target="_blank">Libriciel SCOP</a>
                - <em><?php echo $elapsedTime ?>s</em></p>
        </div>
    <div id="bloc_left">

    </div>

    </div>
</div>
