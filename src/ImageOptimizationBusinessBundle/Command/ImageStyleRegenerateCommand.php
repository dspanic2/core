<?php

// php bin/console image_style:regenerate IMAGE_STYLE_CODE
// php bin/console image_style:regenerate product_list_item

namespace ImageOptimizationBusinessBundle\Command;

use ImageOptimizationBusinessBundle\Managers\ImageStyleManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImageStyleRegenerateCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('image_style:regenerate')
            ->SetDescription('Image style functions')
            ->AddArgument('image_style_code', InputArgument::REQUIRED, 'Image style code');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ImageStyleManager $imageStyleManager */
        $imageStyleManager = $this->getContainer()->get('image_style_manager');

        $imageStyleCode = $input->getArgument('image_style_code');

        $images = $imageStyleManager->getImagesForStyle($imageStyleCode);

        if (!empty($images)) {
            print "\nRegenerating images for style ".$imageStyleCode.":\n";
            foreach ($images as $image) {
                print "\t".$image."\n";
                $imageStyleManager->getImageStyleImageUrl($image, $imageStyleCode, true);
            }
        } else {
            print "No images found...";
        }
        return true;
    }
}
