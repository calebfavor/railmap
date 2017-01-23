<?php

namespace Railroad\Railmap\Entity\Links;

class LinkFactory
{
    /**
     * @param array $allLinks
     * @return OneToOne[]
     */
    static public function getOneToOneLinks(array $allLinks)
    {
        $links = [];

        foreach ($allLinks as $link) {
            if ($link instanceof OneToOne) {
                $links[] = $link;
            }
        }

        return $links;
    }

    /**
     * @param array $allLinks
     * @return OneToMany[]
     */
    static public function getOneToManyLinks(array $allLinks)
    {
        $links = [];

        foreach ($allLinks as $link) {
            if ($link instanceof OneToMany) {
                $links[] = $link;
            }
        }

        return $links;
    }

    /**
     * @param array $allLinks
     * @return ManyToMany[]
     */
    static public function getManyToManyLinks(array $allLinks)
    {
        $links = [];

        foreach ($allLinks as $link) {
            if ($link instanceof ManyToMany) {
                $links[] = $link;
            }
        }

        return $links;
    }
}