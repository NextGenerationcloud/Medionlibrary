<script type="text/html" id="item_tmpl">
    <div class="medionlibrary_single" data-id="<&= id &>">
        <p class="medionlibrary_actions">
            <span class="medionlibrary_delete">
                <img class="svg" src="<?php print_unescaped(OCP\image_path("", "actions/delete.svg")); ?>"
                     title="<?php p($l->t('Delete')); ?>">
            </span>&nbsp;
        </p>
        <p class="medionlibrary_title">
            <a href="<&= checkURL(encodeURI(url)) &>" target="_blank" class="medionlibrary_link" rel="nofollow noopener noreferrer">
                <&= escapeHTML(title == '' ? encodeURI(url) : title ) &>
            </a>
            <span class="medionlibrary_edit medionlibrary_edit_btn">
                <img class="svg" src="<?php print_unescaped(OCP\image_path("", "actions/rename.svg")); ?>" title="<?php p($l->t('Edit')); ?>">
            </span>
        </p>
        <span class="medionlibrary_desc"><&= escapeHTML(description)&> </span>
        <span class="medionlibrary_date"><&= formatDate(added_date) &></span>
    </div>
</script>

<script type="text/html" id="item_form_tmpl">
    <div class="medionlibrary_single_form" data-id="<&= id &>">
        <form method="post" action="medionlibrary" >
            <input type="hidden" name="record_id" value="<&= id &>" />
            <p class="medionlibrary_form_title">
                <input type="text" name="title" placeholder="<?php p($l->t('The title of the page')); ?>"
                       value="<&= escapeHTML(title) &>"/>
            </p>
            <p class="medionlibrary_form_url">
                <input type="text" name="url" placeholder="<?php p($l->t('The address of the page')); ?>"
                       value="<&= encodeURI(url)&>"/>
            </p>
            <div class="medionlibrary_form_tags"><ul>
                    <& for ( var i = 0; i < tags.length; i++ ) { &>
                    <li><&= escapeHTML(tags[i]) &></li>
                    <& } &>
                </ul></div>
            <p class="medionlibrary_form_desc">
                <textarea name="description" placeholder="<?php p($l->t('Description of the page')); ?>"
                          ><&= escapeHTML(description) &></textarea>
            </p>
            <p class="medionlibrary_form_submit">
                <button class="reset" ><?php p($l->t('Cancel')); ?></button>
                <input type="submit" class="primary" value="<?php p($l->t('Save')); ?>">
            </p>
        </form>
    </div>
</script>
<script type="text/html" id="tag_tmpl">
    <li><a href="" class="tag"><&= escapeHTML(tag) &></a>
        <div class="tags_actions">
            <span class="tag_delete">
                <img class="svg" src="<?php print_unescaped(OCP\image_path("", "actions/delete.svg")); ?>"
                     title="<?php p($l->t('Delete')); ?>">
            </span>
            <span class="tag_edit">
                <img class="svg" src="<?php print_unescaped(OCP\image_path("", "actions/rename.svg")); ?>"
                     title="<?php p($l->t('Edit')); ?>">
            </span>
            <em><&= nbr &></em>
        </div>
    </li>
</script>
