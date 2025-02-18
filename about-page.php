<?php if (have_rows('management_team')) || if (have_rows('financial_team')) || if (have_rows('capital_team')): ?>
    <div class="management-team">
        <?php while (have_rows('management_team')) || have_rows('financial_team') || have_rows('capital_team'): the_row(); 
            $headshot = get_sub_field('headshot');
            $headshotUrl = $headshot['url'];
            $headshotAlt = $headshot['alt'];

            $collegeIcon = get_sub_field('college_icon');
            $collegeIconUrl = $collegeIcon['url'];
            $collegeIconAlt = $collegeIcon['alt'];
        ?>
            <div class="team-member">
                <img src="<?php echo $headshotUrl ?>" alt="<?php echo $headshotAlt ?>">
                <h4>
                    <?php the_sub_field('name'); ?>
                </h4>
                <h5>
                    <?php the_sub_field('title'); ?>
                </h5>
                <p><?php the_sub_field('bio'); ?></p>
                <img src="<?php echo $collegeIconUrl ?>" alt="<?php echo $collegeIconAlt ?>">
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <p>No team members found.</p>
<?php endif; ?>


